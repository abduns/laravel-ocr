<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\Tests\TestCase;

class BuilderRuntimeTest extends TestCase
{
    public function test_builder_timeout_override_controls_process_timeout(): void
    {
        $binary = $this->fakeTesseractBinary(3);
        config([
            'ocr.binary' => $binary,
            'ocr.timeout' => 10,
        ]);

        try {
            Ocr::image('/tmp/nonexistent.png')
                ->language('eng')
                ->timeout(1)
                ->run();

            $this->fail('The OCR process did not time out.');
        } catch (OcrProcessingException $e) {
            $this->assertStringContainsString('after 1s', $e->getMessage());
        } finally {
            @unlink($binary);
        }
    }

    private function fakeTesseractBinary(int $sleepSeconds): string
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

sleep(__SLEEP__);
fwrite(STDOUT, "done\n");
PHP;

        file_put_contents($path, str_replace('__SLEEP__', (string) $sleepSeconds, $script));
        chmod($path, 0755);

        return $path;
    }
}
