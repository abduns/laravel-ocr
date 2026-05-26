<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Builders\ImageOcrBuilder;
use Dunn\LaravelOcr\Builders\PdfOcrBuilder;
use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\OcrManager;
use Dunn\LaravelOcr\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_manager_is_bound_as_singleton(): void
    {
        $a = $this->app->make('ocr');
        $b = $this->app->make('ocr');

        $this->assertInstanceOf(OcrManager::class, $a);
        $this->assertSame($a, $b);
    }

    public function test_facade_resolves_to_manager(): void
    {
        $this->assertInstanceOf(OcrManager::class, Ocr::getFacadeRoot());
    }

    public function test_config_is_merged(): void
    {
        $this->assertSame('/usr/bin/tesseract', config('ocr.binary'));
        $this->assertSame('eng', config('ocr.default_language'));
        $this->assertSame(3, config('ocr.default_psm'));
        $this->assertSame(3, config('ocr.default_oem'));
        $this->assertSame(120, config('ocr.timeout'));
        $this->assertSame('local', config('ocr.temp_disk'));
        $this->assertSame('ocr/tmp', config('ocr.temp_path'));
    }

    public function test_image_returns_builder(): void
    {
        $builder = Ocr::image('/tmp/test.png');
        $this->assertInstanceOf(ImageOcrBuilder::class, $builder);
    }

    public function test_pdf_returns_builder(): void
    {
        $builder = Ocr::pdf('/tmp/test.pdf');
        $this->assertInstanceOf(PdfOcrBuilder::class, $builder);
    }

    public function test_empty_path_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('');
    }
}
