<?php

namespace Dunn\LaravelOcr\Tests\Feature;

use Dunn\LaravelOcr\Tests\TestCase;

class LazyValidationTest extends TestCase
{
    public function test_boot_does_not_throw_with_invalid_timeout(): void
    {
        config(['ocr.timeout' => 'invalid']);

        // Service provider boot should not throw
        $manager = $this->app->make('ocr');
        $this->assertNotNull($manager);
    }

    public function test_get_engine_throws_with_invalid_timeout(): void
    {
        config(['ocr.timeout' => 'invalid']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ocr.timeout');

        $manager = $this->app->make('ocr');
        $manager->getEngine();
    }

    public function test_get_engine_throws_with_invalid_psm(): void
    {
        config(['ocr.default_psm' => 99]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ocr.default_psm');

        $manager = $this->app->make('ocr');
        $manager->getEngine();
    }

    public function test_get_engine_throws_with_invalid_oem(): void
    {
        config(['ocr.default_oem' => 10]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ocr.default_oem');

        $manager = $this->app->make('ocr');
        $manager->getEngine();
    }

    public function test_get_temp_path_factory_throws_with_empty_temp_disk(): void
    {
        config(['ocr.temp_disk' => '']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ocr.temp_disk');

        $manager = $this->app->make('ocr');
        $manager->getTempPathFactory();
    }
}
