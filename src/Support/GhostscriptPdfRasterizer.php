<?php

namespace Dunn\LaravelOcr\Support;

use Dunn\LaravelOcr\Exceptions\OcrProcessingException;
use Symfony\Component\Process\Process;

final class GhostscriptPdfRasterizer implements PdfRasterizerBackend
{
    public function pageCount(string $absolutePdfPath): int
    {
        $process = new Process([
            'gs', '-q', '-dNODISPLAY', '-c',
            $this->postScriptString($absolutePdfPath).' (r) file runpdfbegin pdfpagecount = quit',
        ]);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new OcrProcessingException(
                "Could not open PDF '{$absolutePdfPath}' (driver=ghostscript): {$process->getErrorOutput()}"
            );
        }

        $count = (int) trim($process->getOutput());
        if ($count < 1) {
            throw new OcrProcessingException(
                "Could not open PDF '{$absolutePdfPath}' (driver=ghostscript): page count returned {$count}"
            );
        }

        return $count;
    }

    public function rasterize(string $absolutePdfPath, int $page, int $dpi, string $absoluteOutputPath): void
    {
        $process = new Process([
            'gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=png16m',
            "-r{$dpi}",
            "-dFirstPage={$page}",
            "-dLastPage={$page}",
            "-sOutputFile={$absoluteOutputPath}",
            $absolutePdfPath,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new OcrProcessingException(
                "Could not rasterize page {$page} of '{$absolutePdfPath}' (driver=ghostscript): {$process->getErrorOutput()}"
            );
        }
    }

    public function name(): string
    {
        return 'ghostscript';
    }

    private function postScriptString(string $value): string
    {
        return '('.strtr($value, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\n" => '\\n',
            "\r" => '\\r',
        ]).')';
    }
}
