<?php

namespace Dunn\LaravelOcr\Facades;

use Dunn\LaravelOcr\OcrManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Dunn\LaravelOcr\Builders\ImageOcrBuilder image(string $path)
 * @method static \Dunn\LaravelOcr\Builders\PdfOcrBuilder pdf(string $path)
 *
 * @see OcrManager
 */
final class Ocr extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ocr';
    }
}
