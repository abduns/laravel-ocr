<?php

namespace Dunn\LaravelOcr\Tests\Integration;

use Dunn\LaravelOcr\Facades\Ocr;
use Dunn\LaravelOcr\Tests\TestCase;

class RealTesseractIntegrationTest extends TestCase
{
    public function test_real_tesseract_reads_generated_image(): void
    {
        $binary = $this->requireIntegrationBinary();
        $image = $this->createTextPng('HELLO OCR');

        config(['ocr.binary' => $binary]);

        try {
            $text = Ocr::image($image)
                ->language('eng')
                ->psm(6)
                ->timeout(20)
                ->run();

            $normalized = strtoupper($text);
            $this->assertStringContainsString('HELLO', $normalized);
            $this->assertStringContainsString('OCR', $normalized);
        } finally {
            @unlink($image);
        }
    }

    public function test_real_tesseract_reads_generated_pdf_page(): void
    {
        $binary = $this->requireIntegrationBinary();
        $driver = $this->requirePdfBackend();
        $pdf = $this->createImagePdf('PDF OCR');

        config([
            'ocr.binary' => $binary,
            'ocr.pdf.driver' => $driver,
        ]);

        try {
            $pages = Ocr::pdf($pdf)
                ->language('eng')
                ->dpi(200)
                ->timeout(20)
                ->runAll();

            $this->assertArrayHasKey(1, $pages);

            $normalized = strtoupper($pages[1]);
            $this->assertStringContainsString('PDF', $normalized);
            $this->assertStringContainsString('OCR', $normalized);
        } finally {
            @unlink($pdf);
        }
    }

    private function requireIntegrationBinary(): string
    {
        $enabled = filter_var(getenv('OCR_INTEGRATION'), FILTER_VALIDATE_BOOL);
        if (! $enabled) {
            $this->markTestSkipped('Set OCR_INTEGRATION=1 to run real Tesseract integration tests.');
        }

        $binary = getenv('TESSERACT_BIN') ?: '/usr/bin/tesseract';
        if (! is_file($binary) || ! is_executable($binary)) {
            $this->markTestSkipped("Tesseract binary is not executable at {$binary}.");
        }

        return $binary;
    }

    private function requirePdfBackend(): string
    {
        $gs = trim((string) shell_exec('command -v gs 2>/dev/null'));
        if ($gs !== '' && is_executable($gs)) {
            return 'ghostscript';
        }

        if (extension_loaded('imagick')) {
            return 'imagick';
        }

        $this->markTestSkipped('PDF integration requires Ghostscript or Imagick.');
    }

    private function createTextPng(string $text): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to generate OCR image fixtures.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ocr-image-');
        if ($path === false) {
            throw new \RuntimeException('Could not allocate image fixture path.');
        }

        $png = $path.'.png';
        @unlink($path);

        $image = $this->renderTextImage($text, 1400, 360);
        imagepng($image, $png);
        imagedestroy($image);

        return $png;
    }

    private function createImagePdf(string $text): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to generate OCR PDF fixtures.');
        }

        $path = tempnam(sys_get_temp_dir(), 'ocr-pdf-');
        if ($path === false) {
            throw new \RuntimeException('Could not allocate PDF fixture path.');
        }

        $pdfPath = $path.'.pdf';
        @unlink($path);

        $width = 1200;
        $height = 360;
        $image = $this->renderTextImage($text, $width, $height);
        $rgb = $this->rgbBytes($image, $width, $height);
        imagedestroy($image);

        $imageStream = gzcompress($rgb);
        $content = "q\n{$width} 0 0 {$height} 0 0 cm\n/Im0 Do\nQ\n";

        $this->writePdf($pdfPath, [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$width} {$height}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>",
            "<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length ".strlen($imageStream)." >>\nstream\n{$imageStream}\nendstream",
            '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
        ]);

        return $pdfPath;
    }

    /**
     * @return \GdImage
     */
    private function renderTextImage(string $text, int $width, int $height): object
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        $font = $this->fontPath();
        if ($font !== null) {
            imagettftext($image, 96, 0, 70, 220, $black, $font, $text);

            return $image;
        }

        $small = imagecreatetruecolor(280, 80);
        imagefill($small, 0, 0, $white);
        imagestring($small, 5, 20, 28, $text, $black);
        imagecopyresized($image, $small, 0, 0, 0, 0, $width, $height, 280, 80);
        imagedestroy($small);

        return $image;
    }

    private function fontPath(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function rgbBytes(\GdImage $image, int $width, int $height): string
    {
        $bytes = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $bytes .= chr(($rgb >> 16) & 0xFF).chr(($rgb >> 8) & 0xFF).chr($rgb & 0xFF);
            }
        }

        return $bytes;
    }

    /** @param list<string> $objects */
    private function writePdf(string $path, array $objects): void
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number + 1] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents($path, $pdf);
    }
}
