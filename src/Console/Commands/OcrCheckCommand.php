<?php

namespace Dunn\LaravelOcr\Console\Commands;

use Dunn\LaravelOcr\Support\ArgvBuilder;
use Dunn\LaravelOcr\Support\BuilderState;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class OcrCheckCommand extends Command
{
    protected $signature = 'ocr:check
        {--lang= : Language codes to verify, comma separated}
        {--skip-pdf : Skip PDF rasterizer checks}';

    protected $description = 'Check whether Tesseract and OCR dependencies are configured';

    public function handle(Repository $config, FilesystemManager $disks): int
    {
        $this->line('Laravel OCR setup check');

        $ok = true;
        $binary = $this->configuredBinary($config);

        if ($binary === null) {
            $this->failCheck('Tesseract binary', 'ocr.binary must be a non-empty string');
            $ok = false;
        } elseif (! is_file($binary) || ! is_executable($binary)) {
            $this->failCheck('Tesseract binary', "{$binary} is missing or not executable");
            $ok = false;
        } else {
            $this->pass('Tesseract binary', $binary);
            $ok = $this->checkTesseractVersion($binary);

            [$languages, $invalidLanguages] = $this->languagesToCheck($config);
            if ($invalidLanguages !== []) {
                $this->failCheck('Tesseract languages', 'invalid: '.implode(', ', $invalidLanguages));
                $ok = false;
            } elseif ($languages === []) {
                $this->failCheck('Tesseract languages', 'no valid language codes were configured');
                $ok = false;
            } else {
                $ok = $this->checkLanguages($binary, $config, $languages) && $ok;
            }
        }

        $ok = $this->checkTempDisk($config, $disks) && $ok;

        if ($this->option('skip-pdf') === true) {
            $this->line('[SKIP] PDF backend: skipped by --skip-pdf');
        } else {
            $ok = $this->checkPdfBackend($config) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function configuredBinary(Repository $config): ?string
    {
        $binary = $config->get('ocr.binary', '/usr/bin/tesseract');

        return is_string($binary) && trim($binary) !== '' ? trim($binary) : null;
    }

    private function checkTesseractVersion(string $binary): bool
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout(5);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->failCheck('Tesseract version', trim($process->getErrorOutput()) ?: 'binary failed to launch');

            return false;
        }

        $output = $process->getOutput()."\n".$process->getErrorOutput();
        if (! preg_match('/^tesseract\s+v?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/im', $output, $m)) {
            $this->failCheck('Tesseract version', 'could not parse version output');

            return false;
        }

        $version = "{$m['major']}.{$m['minor']}.{$m['patch']}";
        if ((int) $m['major'] < 5) {
            $this->failCheck('Tesseract version', "{$version} is below required minimum 5.0.0");

            return false;
        }

        $this->pass('Tesseract version', $version);

        return true;
    }

    /**
     * @return array{0:list<string>,1:list<string>}
     */
    private function languagesToCheck(Repository $config): array
    {
        $option = $this->option('lang');

        if (is_string($option) && trim($option) !== '') {
            $codes = array_map('trim', explode(',', $option));
        } else {
            $configured = $config->get('ocr.default_language', 'eng');
            $codes = is_array($configured) ? $configured : [$configured];
        }

        $valid = [];
        $invalid = [];
        foreach ($codes as $code) {
            if (is_string($code) && BuilderState::isLanguageCode($code)) {
                $valid[] = $code;

                continue;
            }

            $invalid[] = is_string($code) ? $code : get_debug_type($code);
        }

        return [ArgvBuilder::dedupePreservingOrder($valid), $invalid];
    }

    /**
     * @param  list<string>  $languages
     */
    private function checkLanguages(string $binary, Repository $config, array $languages): bool
    {
        $tessdataPath = $this->configuredTessdataPath($config);

        if ($tessdataPath !== null && ! is_dir($tessdataPath)) {
            $this->failCheck('Tessdata path', "{$tessdataPath} is not a directory");

            return false;
        }

        if ($tessdataPath !== null) {
            $this->pass('Tessdata path', $tessdataPath);
        }

        $args = [$binary, '--list-langs'];
        if ($tessdataPath !== null) {
            $args[] = '--tessdata-dir';
            $args[] = $tessdataPath;
        }

        $process = new Process($args);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->failCheck('Tesseract languages', trim($process->getErrorOutput()) ?: 'could not list languages');

            return false;
        }

        $available = $this->parseLanguageList($process->getOutput()."\n".$process->getErrorOutput());
        if ($available === []) {
            $this->failCheck('Tesseract languages', 'no languages reported by tesseract --list-langs');

            return false;
        }

        $missing = array_values(array_diff($languages, $available));
        if ($missing !== []) {
            $this->failCheck('Tesseract languages', 'missing: '.implode(', ', $missing));

            return false;
        }

        $this->pass('Tesseract languages', implode(', ', $languages));

        return true;
    }

    private function configuredTessdataPath(Repository $config): ?string
    {
        $path = $config->get('ocr.tessdata_path');

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    /**
     * @return list<string>
     */
    private function parseLanguageList(string $output): array
    {
        $languages = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);
            if (BuilderState::isLanguageCode($line)) {
                $languages[] = $line;
            }
        }

        return ArgvBuilder::dedupePreservingOrder($languages);
    }

    private function checkTempDisk(Repository $config, FilesystemManager $disks): bool
    {
        $diskName = $config->get('ocr.temp_disk', 'local');
        if (! is_string($diskName) || trim($diskName) === '') {
            $this->failCheck('Temp disk', 'ocr.temp_disk must be a non-empty string');

            return false;
        }

        try {
            /** @var FilesystemAdapter $disk */
            $disk = $disks->disk($diskName);
            $driver = $disk->getConfig()['driver'] ?? null;
        } catch (\Throwable $e) {
            $this->failCheck('Temp disk', $e->getMessage());

            return false;
        }

        if ($driver !== 'local') {
            $this->failCheck('Temp disk', "{$diskName} uses ".var_export($driver, true).'; expected local');

            return false;
        }

        $this->pass('Temp disk', $diskName);

        return true;
    }

    private function checkPdfBackend(Repository $config): bool
    {
        $driver = $config->get('ocr.pdf.driver', 'auto');
        $driver = is_string($driver) && $driver !== '' ? $driver : 'auto';
        $imagick = extension_loaded('imagick');
        $ghostscript = (new ExecutableFinder)->find('gs');

        if ($driver === 'imagick') {
            return $this->reportPdfBackend($imagick, 'imagick', 'Imagick extension not loaded');
        }

        if ($driver === 'ghostscript') {
            return $this->reportPdfBackend($ghostscript !== null, 'ghostscript', "Ghostscript 'gs' not on PATH");
        }

        if ($imagick) {
            $this->pass('PDF backend', 'imagick');

            return true;
        }

        if ($ghostscript !== null) {
            $this->pass('PDF backend', 'ghostscript');

            return true;
        }

        $this->failCheck('PDF backend', 'install Imagick or Ghostscript, or use --skip-pdf');

        return false;
    }

    private function reportPdfBackend(bool $available, string $backend, string $failure): bool
    {
        if (! $available) {
            $this->failCheck('PDF backend', $failure);

            return false;
        }

        $this->pass('PDF backend', $backend);

        return true;
    }

    private function pass(string $label, string $detail): void
    {
        $this->line("<info>[OK]</info> {$label}: {$detail}");
    }

    private function failCheck(string $label, string $detail): void
    {
        $this->line("<error>[FAIL]</error> {$label}: {$detail}");
    }
}
