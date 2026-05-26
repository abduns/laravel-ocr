<?php

namespace Dunn\LaravelOcr\Console\Commands;

use Dunn\LaravelOcr\Builders\ImageOcrBuilder;
use Dunn\LaravelOcr\Builders\PdfOcrBuilder;
use Dunn\LaravelOcr\OcrManager;
use Illuminate\Console\Command;

final class OcrScanCommand extends Command
{
    protected $signature = 'ocr:scan
        {path : Path to image or PDF}
        {--lang= : Language codes, comma separated}
        {--psm= : Page segmentation mode}
        {--oem= : OCR engine mode}
        {--disk= : Storage disk name}
        {--dpi= : DPI for PDF rasterization}';

    protected $description = 'Run OCR on an image or PDF file';

    public function handle(OcrManager $manager): int
    {
        /** @var string $path */
        $path = $this->argument('path');
        $isPdf = str_ends_with(strtolower($path), '.pdf');

        try {
            if ($isPdf) {
                $builder = $manager->pdf($path);
                $this->applyCommonOptions($builder);
                $dpiOption = $this->option('dpi');
                if ($dpiOption !== null) {
                    $builder->dpi((int) $dpiOption);
                }
                $pages = $builder->runAll();

                $i = 0;
                $total = count($pages);
                foreach ($pages as $text) {
                    $this->output->write($text);
                    if (++$i < $total) {
                        $this->output->write("\f\n");
                    }
                }
                $this->output->write("\n");

                return 0;
            }

            $builder = $manager->image($path);
            $this->applyCommonOptions($builder);
            $text = $builder->run();
            $this->output->write($text."\n");

            return 0;
        } catch (\Throwable $e) {
            $this->error(get_class($e).': '.$e->getMessage());

            return 1;
        }
    }

    private function applyCommonOptions(ImageOcrBuilder|PdfOcrBuilder $builder): void
    {
        $lang = $this->option('lang');
        if ($lang !== null) {
            /** @var string $lang */
            $codes = array_map('trim', explode(',', $lang));
            $builder->languages($codes);
        }
        $psm = $this->option('psm');
        if ($psm !== null) {
            $builder->psm((int) $psm);
        }
        $oem = $this->option('oem');
        if ($oem !== null) {
            $builder->oem((int) $oem);
        }
        $disk = $this->option('disk');
        if ($disk !== null) {
            /** @var string $disk */
            $builder->disk($disk);
        }
    }
}
