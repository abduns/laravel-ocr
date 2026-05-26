---
name: laravel-ocr-development
description: Build and debug Laravel OCR features with dunn/laravel-ocr, including Tesseract setup, image OCR, PDF OCR, filesystem disks, queues, lifecycle events, exceptions, and package verification.
---

# Laravel OCR Development

## When to use this skill

Use this skill when a Laravel app needs OCR through `dunn/laravel-ocr`, or when
you are debugging OCR behavior in an app that already uses the package.

Do not replace this package with a cloud OCR SDK unless the user explicitly asks
for cloud OCR.

## Package model

`dunn/laravel-ocr` is a headless Laravel wrapper around the local Tesseract 5
CLI. It provides:

- Fluent image and PDF OCR builders
- Laravel filesystem disk support
- Queueable OCR jobs
- Lifecycle events
- A publishable `config/ocr.php` file
- An `ocr:scan` Artisan command

Tesseract and PDF rasterizers need real local paths. Keep `ocr.temp_disk` on a
local Laravel filesystem disk. Remote source disks are allowed because the
package copies sources to the local temp disk before OCR.

## Installation

```bash
composer require dunn/laravel-ocr
php artisan vendor:publish --tag=ocr-config
```

Install system dependencies before running real OCR:

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-eng ghostscript

# macOS with Homebrew
brew install tesseract ghostscript
```

## Configuration checklist

- `TESSERACT_BIN` / `ocr.binary`: absolute path to the Tesseract binary.
- `OCR_LANG` / `ocr.default_language`: default language, usually `eng`.
- `TESSDATA_PREFIX` / `ocr.tessdata_path`: directory containing traineddata.
- `ocr.temp_disk`: must use Laravel's local filesystem driver.
- `ocr.temp_path`: local temp folder for copied and rasterized files.
- `OCR_PDF_DRIVER` / `ocr.pdf.driver`: `auto`, `ghostscript`, or `imagick`.
- `OCR_QUEUE_CONNECTION` / `ocr.queue.connection`: deferred OCR queue
  connection.
- `OCR_QUEUE_NAME` / `ocr.queue.name`: default queue name.

Queue workers need the same binaries, language data, environment variables, and
filesystem access as web requests.

## Setup check

Run the package check command before wiring OCR into production or queue
workers:

```bash
php artisan ocr:check
php artisan ocr:check --lang=eng,ind
php artisan ocr:check --skip-pdf
```

`ocr:check` verifies the configured Tesseract binary, Tesseract 5 version,
requested language data, local temp disk, and PDF backend. Use `--skip-pdf`
when the app only needs image OCR.

## Image OCR

```php
use Dunn\LaravelOcr\Facades\Ocr;

$text = Ocr::image(storage_path('app/invoices/invoice.png'))
    ->language('eng')
    ->psm(6)
    ->timeout(30)
    ->run();
```

Use multiple languages in Tesseract order:

```php
$text = Ocr::image($path)
    ->languages(['eng', 'ind'])
    ->run();
```

## PDF OCR

```php
use Dunn\LaravelOcr\Facades\Ocr;

$pages = Ocr::pdf(storage_path('app/contracts/contract.pdf'))
    ->language('eng')
    ->dpi(300)
    ->pages([1, 2, 3])
    ->runAll();
```

`runAll()` returns an array keyed by page number. If `pages()` is omitted, all
pages are OCRed in ascending order.

## Laravel disks

Call `disk()` when the source path belongs to a configured Laravel filesystem
disk:

```php
$text = Ocr::image('uploads/receipt.png')
    ->disk('s3')
    ->language('eng')
    ->run();
```

Do not set `ocr.temp_disk` to S3 or another remote driver.

## Queued OCR

```php
use Dunn\LaravelOcr\Facades\Ocr;

Ocr::pdf('documents/report.pdf')
    ->disk('s3')
    ->language('eng')
    ->onQueue('ocr')
    ->dispatch();
```

Queue name priority is: explicit `onQueue('name')`, then `ocr.queue.name`, then
Laravel's default queue.

## Events

Each OCR invocation emits lifecycle events:

- `Dunn\LaravelOcr\Events\OcrStarted`
- `Dunn\LaravelOcr\Events\OcrCompleted`
- `Dunn\LaravelOcr\Events\OcrFailed`

PDF OCR emits one event sequence per page.

```php
use Dunn\LaravelOcr\Events\OcrCompleted;
use Illuminate\Support\Facades\Event;

Event::listen(OcrCompleted::class, function (OcrCompleted $event) {
    logger()->info('OCR completed', [
        'job_id' => $event->jobId,
        'length' => strlen($event->text),
    ]);
});
```

## Expected exceptions

Catch package exceptions when presenting user-facing errors:

- `Dunn\LaravelOcr\Exceptions\TesseractNotFoundException`
- `Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException`
- `Dunn\LaravelOcr\Exceptions\OcrProcessingException`

## Troubleshooting

For missing binaries, check:

```bash
which tesseract
tesseract --version
```

For language failures, verify that `{code}.traineddata` exists under the
configured tessdata directory, or install the OS language package such as
`tesseract-ocr-eng`.

For PDF failures in CI or server environments, prefer Ghostscript:

```ini
OCR_PDF_DRIVER=ghostscript
```

If OCR times out, increase the builder timeout with `timeout(120)` or set
`ocr.timeout`. Large PDFs are affected by page count, DPI, and image complexity.

## Package verification

When editing the package, run:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
vendor/bin/pint --test
```

Real OCR integration tests are opt-in:

```bash
OCR_INTEGRATION=1 TESSERACT_BIN=/usr/bin/tesseract vendor/bin/phpunit --testsuite Integration
```

In GitHub Actions or other CI, install Tesseract and Ghostscript before running
integration tests.
