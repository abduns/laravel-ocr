<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Builders\ImageOcrBuilder;
use Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException;
use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\Tests\TestCase;

class BuilderValidationTest extends TestCase
{
    public function test_psm_rejects_out_of_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->psm(14);
    }

    public function test_oem_rejects_out_of_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->oem(4);
    }

    public function test_timeout_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->timeout(0);
    }

    public function test_timeout_rejects_above_3600(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->timeout(3601);
    }

    public function test_language_rejects_invalid_code(): void
    {
        $this->expectException(UnsupportedLanguageException::class);
        Ocr::image('/tmp/x.png')->language('INVALID');
    }

    public function test_languages_reject_non_string_code(): void
    {
        $this->expectException(UnsupportedLanguageException::class);
        Ocr::image('/tmp/x.png')->languages([123]);
    }

    public function test_languages_rejects_empty_array(): void
    {
        $this->expectException(UnsupportedLanguageException::class);
        Ocr::image('/tmp/x.png')->languages([]);
    }

    public function test_disk_rejects_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::image('/tmp/x.png')->disk('');
    }

    public function test_dpi_rejects_below_72(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->dpi(50);
    }

    public function test_dpi_rejects_above_1200(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->dpi(1201);
    }

    public function test_pages_rejects_empty_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->pages([]);
    }

    public function test_pages_rejects_duplicates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->pages([1, 1, 2]);
    }

    public function test_pages_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->pages([0, 1]);
    }

    public function test_pages_rejects_non_integer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ocr::pdf('/tmp/x.pdf')->pages([1, '2']);
    }

    public function test_image_run_rejects_invalid_config_default_language(): void
    {
        config(['ocr.default_language' => 'INVALID']);

        $this->expectException(UnsupportedLanguageException::class);
        Ocr::image('/tmp/x.png')->run();
    }

    public function test_pdf_run_rejects_invalid_config_default_language(): void
    {
        config(['ocr.default_language' => ['eng', 'INVALID']]);

        $this->expectException(UnsupportedLanguageException::class);
        Ocr::pdf('/tmp/x.pdf')->runAll();
    }

    public function test_fluent_chaining_returns_same_instance(): void
    {
        $builder = Ocr::image('/tmp/x.png');
        $result = $builder->language('eng')->psm(6)->oem(1)->timeout(60);

        $this->assertSame($builder, $result);
    }

    public function test_valid_language_codes_accepted(): void
    {
        $builder = Ocr::image('/tmp/x.png');
        $result = $builder->languages(['eng', 'ind', 'chi_sim']);

        $this->assertInstanceOf(ImageOcrBuilder::class, $result);
    }
}
