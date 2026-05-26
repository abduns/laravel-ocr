<?php

namespace Dunn\LaravelOcr\Tests;

use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\OcrServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [OcrServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Ocr' => Ocr::class];
    }
}
