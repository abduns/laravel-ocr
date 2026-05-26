<?php

namespace Dunn\LaravelOcr\Support;

interface PdfRasterizerBackend
{
    public function pageCount(string $absolutePdfPath): int;

    public function rasterize(string $absolutePdfPath, int $page, int $dpi, string $absoluteOutputPath): void;

    public function name(): string;
}
