<?php

namespace Dunn\LaravelOcr\Builders;

use Dunn\LaravelOcr\Events\OcrCompleted;
use Dunn\LaravelOcr\Events\OcrFailed;
use Dunn\LaravelOcr\Events\OcrStarted;
use Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException;
use Dunn\LaravelOcr\OcrManager;
use Dunn\LaravelOcr\Support\ArgvBuilder;
use Dunn\LaravelOcr\Support\BuilderState;

final class ImageOcrBuilder
{
    private BuilderState $state;

    public function __construct(BuilderState $state, private readonly OcrManager $manager)
    {
        $this->state = $state;
    }

    public function language(string $code): self
    {
        return $this->languages([$code]);
    }

    /** @param list<string> $codes */
    public function languages(array $codes): self
    {
        $this->state = $this->state->with(['languages' => $this->validatedLanguages($codes)]);

        return $this;
    }

    public function psm(int $mode): self
    {
        if ($mode < 0 || $mode > 13) {
            throw new \InvalidArgumentException("PSM must be 0..13; got {$mode}");
        }
        $this->state = $this->state->with(['psm' => $mode]);

        return $this;
    }

    public function oem(int $mode): self
    {
        if ($mode < 0 || $mode > 3) {
            throw new \InvalidArgumentException("OEM must be 0..3; got {$mode}");
        }
        $this->state = $this->state->with(['oem' => $mode]);

        return $this;
    }

    public function disk(string $disk): self
    {
        if (trim($disk) === '') {
            throw new \InvalidArgumentException('Disk name must be a non-empty string');
        }
        $this->state = $this->state->with(['disk' => $disk]);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        if ($seconds < 1 || $seconds > 3600) {
            throw new \InvalidArgumentException("Timeout must be 1..3600; got {$seconds}");
        }
        $this->state = $this->state->with(['timeout' => $seconds]);

        return $this;
    }

    public function tessdataPath(string $path): self
    {
        $this->state = $this->state->with(['tessdataPath' => $path]);

        return $this;
    }

    public function onQueue(?string $queue = null): QueueDispatcher
    {
        return new QueueDispatcher($this->state, $queue);
    }

    public function run(): string
    {
        $engine = $this->manager->getEngine();
        $resolver = $this->manager->getSourceResolver();
        $events = $this->manager->getEvents();
        $jobIds = $this->manager->getJobIdGenerator();
        $config = $this->manager->getConfig();

        // Resolve effective state with config defaults
        $effectiveState = $this->resolveDefaults($config);

        // Check traineddata existence
        $td = $this->resolveTessdataPath($effectiveState, $config);
        $this->checkTraineddata($effectiveState, $td);

        $ext = pathinfo($effectiveState->source, PATHINFO_EXTENSION) ?: 'png';
        $resolved = $resolver->resolve($effectiveState, $ext);

        $argv = ArgvBuilder::buildImage($config['binary'], $effectiveState, $resolved['absolutePath'], $td);
        $jobId = $jobIds->next();
        $sourceId = $effectiveState->disk !== null
            ? "{$effectiveState->disk}:{$effectiveState->source}"
            : $effectiveState->source;

        $events->dispatch(new OcrStarted($jobId, $sourceId));

        try {
            $text = $engine->run($argv, $effectiveState->timeout);
            $resolved['cleanup']();
            $events->dispatch(new OcrCompleted($jobId, $text));

            return $text;
        } catch (\Throwable $e) {
            $resolved['cleanup']();
            $events->dispatch(new OcrFailed($jobId, $e));
            throw $e;
        }
    }

    public function getState(): BuilderState
    {
        return $this->state;
    }

    /** @param array<string, mixed> $config */
    private function resolveDefaults(array $config): BuilderState
    {
        $languages = $this->state->languages;
        if ($languages === []) {
            $lang = $config['default_language'] ?? 'eng';
            $languages = is_array($lang) ? $lang : [$lang];
        }

        $languages = $this->validatedLanguages($languages);

        return $this->state->with([
            'languages' => $languages,
            'psm' => $this->state->psm,
            'oem' => $this->state->oem,
            'timeout' => $this->state->timeout,
        ]);
    }

    /**
     * @param  array<int, mixed>  $languages
     * @return list<string>
     */
    private function validatedLanguages(array $languages): array
    {
        if ($languages === []) {
            throw new UnsupportedLanguageException('Language codes array must not be empty');
        }

        $validated = [];
        foreach ($languages as $code) {
            if (! is_string($code)) {
                throw new UnsupportedLanguageException('Invalid language code type: '.get_debug_type($code));
            }

            if (! BuilderState::isLanguageCode($code)) {
                throw new UnsupportedLanguageException("Invalid language code: '{$code}'");
            }

            $validated[] = $code;
        }

        return ArgvBuilder::dedupePreservingOrder($validated);
    }

    /** @param array<string, mixed> $config */
    private function resolveTessdataPath(BuilderState $state, array $config): ?string
    {
        if ($state->tessdataPath !== null && $state->tessdataPath !== '') {
            return $state->tessdataPath;
        }
        $configPath = $config['tessdata_path'] ?? null;
        if (is_string($configPath) && $configPath !== '') {
            return $configPath;
        }

        return null;
    }

    private function checkTraineddata(BuilderState $state, ?string $td): void
    {
        if ($td === null || $td === '') {
            return; // Defer to TESSDATA_PREFIX env
        }
        foreach ($state->languages as $code) {
            $candidate = rtrim($td, '/').'/'.$code.'.traineddata';
            if (! is_file($candidate)) {
                throw new UnsupportedLanguageException(
                    "Language '{$code}' has no traineddata under '{$td}'"
                );
            }
        }
    }
}
