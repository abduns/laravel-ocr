<?php

namespace Dunn\LaravelOcr\Builders;

use Dunn\LaravelOcr\Events\OcrCompleted;
use Dunn\LaravelOcr\Events\OcrFailed;
use Dunn\LaravelOcr\Events\OcrStarted;
use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException;
use Dunn\LaravelOcr\OcrManager;
use Dunn\LaravelOcr\Support\ArgvBuilder;
use Dunn\LaravelOcr\Support\BuilderState;
use Illuminate\Support\Facades\Log;

final class PdfOcrBuilder
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

    public function dpi(int $dpi): self
    {
        if ($dpi < 72 || $dpi > 1200) {
            throw new \InvalidArgumentException("DPI must be 72..1200; got {$dpi}");
        }
        $this->state = $this->state->with(['dpi' => $dpi]);

        return $this;
    }

    /** @param array<int, mixed> $pageNumbers */
    public function pages(array $pageNumbers): self
    {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('Pages must be a non-empty array of positive integers');
        }
        foreach ($pageNumbers as $p) {
            if (! is_int($p) || $p < 1) {
                throw new \InvalidArgumentException(
                    'Page numbers must be positive integers; got '.var_export($p, true)
                );
            }
        }
        if (count($pageNumbers) !== count(array_unique($pageNumbers))) {
            throw new \InvalidArgumentException('Pages must not contain duplicates');
        }
        $this->state = $this->state->with(['pages' => array_values($pageNumbers)]);

        return $this;
    }

    public function onQueue(?string $queue = null): QueueDispatcher
    {
        return new QueueDispatcher($this->state, $queue);
    }

    /** @return array<int, string> */
    public function runAll(): array
    {
        $engine = $this->manager->getEngine();
        $resolver = $this->manager->getSourceResolver();
        $rasterizer = $this->manager->getPdfRasterizer();
        $tempPaths = $this->manager->getTempPathFactory();
        $events = $this->manager->getEvents();
        $jobIds = $this->manager->getJobIdGenerator();
        $config = $this->manager->getConfig();

        $effectiveState = $this->resolveDefaults($config);
        $td = $this->resolveTessdataPath($effectiveState, $config);
        $this->checkTraineddata($effectiveState, $td);

        $dpi = $effectiveState->dpi ?? ($config['pdf']['default_dpi'] ?? 300);

        // Resolve source PDF to local path
        $resolved = $resolver->resolve($effectiveState, 'pdf');
        $pdfPath = $resolved['absolutePath'];

        /** @var list<string> $tempFiles */
        $tempFiles = [];
        $cleanup = static function () use (&$tempFiles): void {
            /** @var list<string> $tempFiles */
            foreach ($tempFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $tempFiles = [];
        };

        try {
            $totalPages = $rasterizer->pageCount($pdfPath);
            $selection = $effectiveState->pages ?? range(1, $totalPages);
            sort($selection);

            // Validate page range
            foreach ($selection as $p) {
                if ($p > $totalPages) {
                    throw new OcrProcessingException(
                        "Requested page {$p} exceeds PDF page count {$totalPages} for '{$effectiveState->source}'"
                    );
                }
            }

            $results = [];
            $tempDiskName = $tempPaths->getDiskName();
            $tempDisk = app('filesystem')->disk($tempDiskName);

            foreach ($selection as $page) {
                $tempRelPath = $tempPaths->unique('png', "p{$page}");
                $tempAbsPath = $tempDisk->path($tempRelPath);

                $dir = dirname($tempAbsPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $rasterizer->rasterize($pdfPath, $page, $dpi, $tempAbsPath);
                $tempFiles[] = $tempAbsPath;

                $argv = ArgvBuilder::buildImage($config['binary'], $effectiveState, $tempAbsPath, $td);
                $jobId = $jobIds->next();
                $sourceId = ($effectiveState->disk !== null ? "{$effectiveState->disk}:" : '')."{$effectiveState->source}:page:{$page}";

                $events->dispatch(new OcrStarted($jobId, $sourceId));

                try {
                    $text = $engine->run($argv, $effectiveState->timeout);
                    $events->dispatch(new OcrCompleted($jobId, $text));
                    $results[$page] = $text;
                } catch (\Throwable $e) {
                    $events->dispatch(new OcrFailed($jobId, $e));
                    throw $e;
                }
            }

            // Success cleanup
            $resolved['cleanup']();
            try {
                $cleanup();
            } catch (\Throwable $e) {
                Log::warning('ocr cleanup failed', [
                    'disk' => $tempDiskName,
                    'paths' => $tempFiles,
                    'error' => $e->getMessage(),
                ]);
            }

            return $results;
        } catch (\Throwable $e) {
            $resolved['cleanup']();
            try {
                $cleanup();
            } catch (\Throwable) {
            }
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

        return $this->state->with(['languages' => $this->validatedLanguages($languages)]);
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

        return (is_string($configPath) && $configPath !== '') ? $configPath : null;
    }

    private function checkTraineddata(BuilderState $state, ?string $td): void
    {
        if ($td === null || $td === '') {
            return;
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
