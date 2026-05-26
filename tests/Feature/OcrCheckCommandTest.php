<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Tests\TestCase;

class OcrCheckCommandTest extends TestCase
{
    public function test_ocr_check_passes_with_configured_tesseract(): void
    {
        $binary = $this->fakeTesseractBinary(['eng', 'ind']);
        config([
            'ocr.binary' => $binary,
            'ocr.default_language' => 'eng',
            'ocr.tessdata_path' => null,
        ]);

        try {
            $this->artisan('ocr:check', ['--skip-pdf' => true])
                ->expectsOutputToContain('[OK] Tesseract binary')
                ->expectsOutputToContain('[OK] Tesseract version: 5.3.0')
                ->expectsOutputToContain('[OK] Tesseract languages: eng')
                ->assertExitCode(0);
        } finally {
            @unlink($binary);
        }
    }

    public function test_ocr_check_can_verify_explicit_languages(): void
    {
        $binary = $this->fakeTesseractBinary(['eng', 'ind']);
        config([
            'ocr.binary' => $binary,
            'ocr.default_language' => 'eng',
            'ocr.tessdata_path' => null,
        ]);

        try {
            $this->artisan('ocr:check', [
                '--lang' => 'eng,ind',
                '--skip-pdf' => true,
            ])
                ->expectsOutputToContain('[OK] Tesseract languages: eng, ind')
                ->assertExitCode(0);
        } finally {
            @unlink($binary);
        }
    }

    public function test_ocr_check_fails_when_language_is_missing(): void
    {
        $binary = $this->fakeTesseractBinary(['eng']);
        config([
            'ocr.binary' => $binary,
            'ocr.default_language' => 'ind',
            'ocr.tessdata_path' => null,
        ]);

        try {
            $this->artisan('ocr:check', ['--skip-pdf' => true])
                ->expectsOutputToContain('[FAIL] Tesseract languages: missing: ind')
                ->assertExitCode(1);
        } finally {
            @unlink($binary);
        }
    }

    public function test_ocr_check_fails_when_language_code_is_invalid(): void
    {
        $binary = $this->fakeTesseractBinary(['eng']);
        config([
            'ocr.binary' => $binary,
            'ocr.default_language' => 'eng',
            'ocr.tessdata_path' => null,
        ]);

        try {
            $this->artisan('ocr:check', [
                '--lang' => 'eng,bad-code',
                '--skip-pdf' => true,
            ])
                ->expectsOutputToContain('[FAIL] Tesseract languages: invalid: bad-code')
                ->assertExitCode(1);
        } finally {
            @unlink($binary);
        }
    }

    public function test_ocr_check_fails_when_binary_is_missing(): void
    {
        config([
            'ocr.binary' => '/tmp/missing-tesseract-binary',
            'ocr.tessdata_path' => null,
        ]);

        $this->artisan('ocr:check', ['--skip-pdf' => true])
            ->expectsOutputToContain('[FAIL] Tesseract binary')
            ->assertExitCode(1);
    }

    /**
     * @param  list<string>  $languages
     */
    private function fakeTesseractBinary(array $languages): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr-bin-');
        if ($path === false) {
            throw new \RuntimeException('Could not allocate temp binary path.');
        }

        $script = <<<'PHP'
#!/usr/bin/env php
<?php
if (($argv[1] ?? null) === '--version') {
    fwrite(STDOUT, "tesseract 5.3.0\n");
    exit(0);
}

if (($argv[1] ?? null) === '--list-langs') {
    fwrite(STDOUT, "List of available languages in \"/tmp\" (__COUNT__):\n__LANGUAGES__\n");
    exit(0);
}

fwrite(STDERR, "unexpected command\n");
exit(1);
PHP;

        $script = str_replace(
            ['__COUNT__', '__LANGUAGES__'],
            [(string) count($languages), implode("\n", $languages)],
            $script
        );

        file_put_contents($path, $script);
        chmod($path, 0755);

        return $path;
    }
}
