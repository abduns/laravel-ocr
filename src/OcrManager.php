<?php

namespace Dunn\LaravelOcr;

use Dunn\LaravelOcr\Builders\ImageOcrBuilder;
use Dunn\LaravelOcr\Builders\PdfOcrBuilder;
use Dunn\LaravelOcr\Engines\TesseractEngine;
use Dunn\LaravelOcr\Support\BuilderState;
use Dunn\LaravelOcr\Support\JobIdGenerator;
use Dunn\LaravelOcr\Support\PdfRasterizer;
use Dunn\LaravelOcr\Support\SourceResolver;
use Dunn\LaravelOcr\Support\TempPathFactory;
use Dunn\LaravelOcr\Support\TesseractVersionChecker;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;

final class OcrManager
{
    private ?TesseractEngine $engine = null;

    private ?SourceResolver $sourceResolver = null;

    private ?PdfRasterizer $pdfRasterizer = null;

    private ?TempPathFactory $tempPathFactory = null;

    private ?JobIdGenerator $jobIdGenerator = null;

    public function __construct(
        private readonly Repository $config,
        private readonly Dispatcher $events,
        private readonly FilesystemManager $disks,
    ) {}

    public function image(string $path): ImageOcrBuilder
    {
        if ($path === '' || strlen($path) > 4096) {
            throw new \InvalidArgumentException('Source path must be 1..4096 characters');
        }

        $state = new BuilderState(
            kind: 'image',
            source: $path,
            psm: $this->config->get('ocr.default_psm', 3),
            oem: $this->config->get('ocr.default_oem', 3),
            timeout: $this->config->get('ocr.timeout', 120),
        );

        return new ImageOcrBuilder($state, $this);
    }

    public function pdf(string $path): PdfOcrBuilder
    {
        if ($path === '' || strlen($path) > 4096) {
            throw new \InvalidArgumentException('Source path must be 1..4096 characters');
        }

        $state = new BuilderState(
            kind: 'pdf',
            source: $path,
            psm: $this->config->get('ocr.default_psm', 3),
            oem: $this->config->get('ocr.default_oem', 3),
            timeout: $this->config->get('ocr.timeout', 120),
        );

        return new PdfOcrBuilder($state, $this);
    }

    public function getEngine(): TesseractEngine
    {
        if ($this->engine !== null) {
            return $this->engine;
        }

        $timeout = $this->config->get('ocr.timeout');
        if (! is_int($timeout) || $timeout < 1 || $timeout > 3600) {
            throw new \InvalidArgumentException(
                'ocr.timeout must be an integer in [1..3600]; got '.var_export($timeout, true)
            );
        }

        $psm = $this->config->get('ocr.default_psm');
        if (! is_int($psm) || $psm < 0 || $psm > 13) {
            throw new \InvalidArgumentException(
                'ocr.default_psm must be an integer in [0..13]; got '.var_export($psm, true)
            );
        }

        $oem = $this->config->get('ocr.default_oem');
        if (! is_int($oem) || $oem < 0 || $oem > 3) {
            throw new \InvalidArgumentException(
                'ocr.default_oem must be an integer in [0..3]; got '.var_export($oem, true)
            );
        }

        $binary = $this->config->get('ocr.binary', '/usr/bin/tesseract');

        return $this->engine = new TesseractEngine(
            timeout: $timeout,
            versionChecker: new TesseractVersionChecker($binary),
        );
    }

    public function getSourceResolver(): SourceResolver
    {
        if ($this->sourceResolver !== null) {
            return $this->sourceResolver;
        }

        return $this->sourceResolver = new SourceResolver($this->disks, $this->getTempPathFactory());
    }

    public function getPdfRasterizer(): PdfRasterizer
    {
        if ($this->pdfRasterizer !== null) {
            return $this->pdfRasterizer;
        }

        return $this->pdfRasterizer = new PdfRasterizer($this->config);
    }

    public function getTempPathFactory(): TempPathFactory
    {
        if ($this->tempPathFactory !== null) {
            return $this->tempPathFactory;
        }

        $diskName = $this->config->get('ocr.temp_disk', 'local');
        $tempPath = $this->config->get('ocr.temp_path', 'ocr/tmp');

        if (! is_string($diskName) || trim($diskName) === '') {
            throw new \InvalidArgumentException(
                'ocr.temp_disk must be a non-empty string; got '.var_export($diskName, true)
            );
        }

        if (! is_string($tempPath) || trim($tempPath) === '') {
            throw new \InvalidArgumentException(
                'ocr.temp_path must be a non-empty string; got '.var_export($tempPath, true)
            );
        }

        /** @var FilesystemAdapter $disk */
        $disk = $this->disks->disk($diskName);
        $driver = $disk->getConfig()['driver'] ?? null;
        if ($driver !== 'local') {
            throw new \InvalidArgumentException(
                "ocr.temp_disk must use the local driver; disk '{$diskName}' uses ".var_export($driver, true)
            );
        }

        return $this->tempPathFactory = new TempPathFactory($diskName, $tempPath);
    }

    public function getJobIdGenerator(): JobIdGenerator
    {
        if ($this->jobIdGenerator !== null) {
            return $this->jobIdGenerator;
        }

        return $this->jobIdGenerator = new JobIdGenerator;
    }

    public function getEvents(): Dispatcher
    {
        return $this->events;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config->get('ocr', []);
    }

    /** @param array<string, mixed> $payload */
    public function rebuildImageBuilder(array $payload): ImageOcrBuilder
    {
        $state = BuilderState::fromPayload($payload);

        return new ImageOcrBuilder($state, $this);
    }

    /** @param array<string, mixed> $payload */
    public function rebuildPdfBuilder(array $payload): PdfOcrBuilder
    {
        $state = BuilderState::fromPayload($payload);

        return new PdfOcrBuilder($state, $this);
    }
}
