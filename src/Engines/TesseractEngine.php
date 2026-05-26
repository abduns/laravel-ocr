<?php

namespace Dunn\LaravelOcr\Engines;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Dunn\LaravelOcr\Support\TesseractVersionChecker;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class TesseractEngine
{
    private bool $versionChecked = false;

    public function __construct(
        private readonly int $timeout,
        private readonly TesseractVersionChecker $versionChecker,
    ) {}

    public function ensureSupported(): void
    {
        if (! $this->versionChecked) {
            $this->versionChecker->ensureSupported();
            $this->versionChecked = true;
        }
    }

    /**
     * @param  list<string>  $argv  Full argv including binary path
     */
    public function run(array $argv, ?int $timeout = null): string
    {
        $this->ensureSupported();

        $process = new Process($argv);
        $effectiveTimeout = $timeout ?? $this->timeout;
        $process->setTimeout($effectiveTimeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new OcrProcessingException(
                "Tesseract timed out after {$effectiveTimeout}s; argv=".json_encode($argv)
            );
        }

        if ($process->getExitCode() !== 0) {
            throw new OcrProcessingException(
                "Tesseract exited with status {$process->getExitCode()}; stderr={$process->getErrorOutput()}; argv=".json_encode($argv)
            );
        }

        return $process->getOutput();
    }
}
