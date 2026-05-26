<?php

namespace Dunn\LaravelOcr\Support;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Illuminate\Contracts\Config\Repository;

final class PdfRasterizer
{
    private ?PdfRasterizerBackend $backend = null;

    public function __construct(private readonly Repository $config) {}

    public function probeBackend(): PdfRasterizerBackend
    {
        if ($this->backend !== null) {
            return $this->backend;
        }

        $selected = $this->config->get('ocr.pdf.driver', 'auto');

        if ($selected === 'imagick') {
            if (! extension_loaded('imagick')) {
                throw new OcrProcessingException('Imagick extension not loaded');
            }

            return $this->backend = new ImagickPdfRasterizer;
        }

        if ($selected === 'ghostscript') {
            if (! $this->gsAvailable()) {
                throw new OcrProcessingException("Ghostscript 'gs' not on PATH");
            }

            return $this->backend = new GhostscriptPdfRasterizer;
        }

        // auto: Imagick first, Ghostscript fallback
        if (extension_loaded('imagick')) {
            return $this->backend = new ImagickPdfRasterizer;
        }

        if ($this->gsAvailable()) {
            return $this->backend = new GhostscriptPdfRasterizer;
        }

        throw new OcrProcessingException(
            'No PDF rasterization backend available; install Imagick PHP extension or Ghostscript'
        );
    }

    public function pageCount(string $absolutePdfPath): int
    {
        return $this->probeBackend()->pageCount($absolutePdfPath);
    }

    public function rasterize(string $absolutePdfPath, int $page, int $dpi, string $absoluteOutputPath): void
    {
        $this->probeBackend()->rasterize($absolutePdfPath, $page, $dpi, $absoluteOutputPath);
    }

    private function gsAvailable(): bool
    {
        $path = trim((string) shell_exec('which gs 2>/dev/null'));

        return $path !== '' && is_executable($path);
    }
}
