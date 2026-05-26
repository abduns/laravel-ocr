<?php

namespace Dunn\LaravelOcr\Support;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;

final class ImagickPdfRasterizer implements PdfRasterizerBackend
{
    public function pageCount(string $absolutePdfPath): int
    {
        try {
            $im = new \Imagick;
            $im->pingImage($absolutePdfPath);
            $count = $im->getNumberImages();
            $im->clear();
            $im->destroy();

            return $count;
        } catch (\ImagickException $e) {
            throw new OcrProcessingException(
                "Could not open PDF '{$absolutePdfPath}' (driver=imagick): {$e->getMessage()}"
            );
        }
    }

    public function rasterize(string $absolutePdfPath, int $page, int $dpi, string $absoluteOutputPath): void
    {
        try {
            $im = new \Imagick;
            $im->setResolution($dpi, $dpi);
            $im->readImage($absolutePdfPath.'['.($page - 1).']');
            $im->setImageFormat('png');
            $im->writeImage($absoluteOutputPath);
            $im->clear();
            $im->destroy();
        } catch (\ImagickException $e) {
            throw new OcrProcessingException(
                "Could not rasterize page {$page} of '{$absolutePdfPath}' (driver=imagick): {$e->getMessage()}"
            );
        }
    }

    public function name(): string
    {
        return 'imagick';
    }
}
