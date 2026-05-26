<?php

namespace Dunn\LaravelOcr\Support;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Dunn\LaravelOcr\Exceptions\TesseractNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class TesseractVersionChecker
{
    /** @var array{major:int, minor:int, patch:int}|null */
    private ?array $cached = null;

    public function __construct(private readonly string $binary) {}

    /** @return array{major:int, minor:int, patch:int} */
    public function detect(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        if (! is_file($this->binary) || ! is_executable($this->binary)) {
            throw new TesseractNotFoundException(
                "Tesseract binary not found or not executable: {$this->binary}"
            );
        }

        $process = new Process([$this->binary, '--version']);
        $process->setTimeout(5);

        try {
            $process->mustRun();
        } catch (ProcessFailedException|\RuntimeException $e) {
            throw new TesseractNotFoundException(
                "Tesseract binary failed to launch: {$this->binary} ({$e->getMessage()})"
            );
        }

        $output = $process->getOutput()."\n".$process->getErrorOutput();

        if (! preg_match('/^tesseract\s+v?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/im', $output, $m)) {
            throw new OcrProcessingException(
                'Could not parse Tesseract version from: '.trim($output)
            );
        }

        $this->cached = [
            'major' => (int) $m['major'],
            'minor' => (int) $m['minor'],
            'patch' => (int) $m['patch'],
        ];

        return $this->cached;
    }

    public function ensureSupported(): void
    {
        $v = $this->detect();

        if ($v['major'] < 5) {
            throw new OcrProcessingException(
                "Tesseract version {$v['major']}.{$v['minor']}.{$v['patch']} is below required minimum 5.0.0"
            );
        }
    }
}
