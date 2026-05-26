## Laravel OCR

This package provides a headless Laravel wrapper around the local Tesseract 5
CLI for image and PDF OCR.

### Core conventions

- Use the `Dunn\LaravelOcr\Facades\Ocr` facade for application code examples.
- Keep namespaces under `Dunn\LaravelOcr`.
- Keep Composer package and repository references as `abduns/laravel-ocr`.
- Treat OCR as a local process integration, not a cloud OCR integration.
- Keep `ocr.temp_disk` on a local Laravel filesystem disk because Tesseract and
  PDF rasterizers need real local paths.
- Queue workers must have the same Tesseract binary, traineddata, PDF tools,
  environment variables, and filesystem access as web requests.
- Remote source disks are supported; sources are copied to the configured local
  temp disk before OCR.
- Configuration validation is lazy, so missing system dependencies usually fail
  when OCR executes, not when Laravel boots.

### Installation

@verbatim
<code-snippet name="Install Laravel OCR" lang="bash">
composer require abduns/laravel-ocr
php artisan vendor:publish --tag=ocr-config
</code-snippet>
@endverbatim

Install Tesseract 5 and at least one language pack before running real OCR.
PDF support also requires Ghostscript or a working Imagick PDF policy.

### Setup check

Use `ocr:check` to verify the configured Tesseract binary, Tesseract 5 version,
requested language data, local temp disk, and PDF backend.

@verbatim
<code-snippet name="Check Laravel OCR setup" lang="bash">
php artisan ocr:check
php artisan ocr:check --lang=eng,ind
php artisan ocr:check --skip-pdf
</code-snippet>
@endverbatim

### Image OCR

@verbatim
<code-snippet name="Run image OCR" lang="php">
use Dunn\LaravelOcr\Facades\Ocr;

$text = Ocr::image(storage_path('app/invoices/invoice.png'))
    ->language('eng')
    ->psm(6)
    ->timeout(30)
    ->run();
</code-snippet>
@endverbatim

### PDF OCR

@verbatim
<code-snippet name="Run PDF OCR" lang="php">
use Dunn\LaravelOcr\Facades\Ocr;

$pages = Ocr::pdf(storage_path('app/contracts/contract.pdf'))
    ->language('eng')
    ->dpi(300)
    ->pages([1, 2, 3])
    ->runAll();
</code-snippet>
@endverbatim

The PDF result is keyed by page number. If `pages()` is omitted, every page is
OCRed in ascending order.

### Queued OCR

@verbatim
<code-snippet name="Dispatch queued OCR" lang="php">
use Dunn\LaravelOcr\Facades\Ocr;

Ocr::pdf('documents/report.pdf')
    ->disk('s3')
    ->language('eng')
    ->onQueue('ocr')
    ->dispatch();
</code-snippet>
@endverbatim

### Events

Listen for OCR lifecycle events when an app needs progress logging, analytics,
or follow-up processing:

- `Dunn\LaravelOcr\Events\OcrStarted`
- `Dunn\LaravelOcr\Events\OcrCompleted`
- `Dunn\LaravelOcr\Events\OcrFailed`

PDF OCR emits one event sequence per page.

### Errors

Catch these package exceptions for user-facing error handling:

- `Dunn\LaravelOcr\Exceptions\TesseractNotFoundException`
- `Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException`
- `Dunn\LaravelOcr\Exceptions\OcrProcessingException`
