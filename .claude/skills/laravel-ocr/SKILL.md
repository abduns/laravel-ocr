---
name: laravel-ocr
description: "Use when adding, configuring, testing, or debugging OCR in a Laravel app with `dunn/laravel-ocr`. Covers local Tesseract 5 setup, image and PDF OCR, filesystem disks, queue dispatching, lifecycle events, exceptions, CI dependencies, and package development checks. Do not use for cloud OCR SDKs unless the user explicitly asks for cloud OCR."
license: MIT
metadata:
  author: dunn
---

# Laravel OCR

Read the repository root `AGENTS.md` first. It is the canonical, tool-agnostic
AI instruction file for this package. Use the notes below only when `AGENTS.md`
is unavailable.

This app uses `dunn/laravel-ocr`, a headless Laravel wrapper around the local
Tesseract 5 CLI. It provides a fluent API, queueable jobs, lifecycle events,
filesystem disk support, a publishable config file, and an `ocr:scan` Artisan
command.

## Core rules

- Treat OCR as a local process integration. Do not add routes, views, frontend
  assets, cloud OCR SDKs, or language downloaders unless the user asks for them.
- Tesseract and PDF rasterizers need real local paths. Keep `ocr.temp_disk`
  configured to a local Laravel filesystem disk.
- Source files may come from remote disks; the package streams them to the local
  temp disk before running OCR.
- Validation is lazy: a Laravel app can boot without Tesseract installed, but
  OCR fails when execution starts.
- Queue workers need the same system binaries, language data, environment
  variables, and filesystem access as web requests.

## Install and configure

Install the package:

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

Important config and environment values:

| Value | Purpose |
| --- | --- |
| `TESSERACT_BIN` / `ocr.binary` | Absolute path to the Tesseract binary |
| `OCR_LANG` / `ocr.default_language` | Default Tesseract language, usually `eng` |
| `TESSDATA_PREFIX` / `ocr.tessdata_path` | Directory containing `*.traineddata` files |
| `ocr.temp_disk` | Must be a local disk |
| `ocr.temp_path` | Local temp folder for copied/rasterized files |
| `OCR_PDF_DRIVER` / `ocr.pdf.driver` | `auto`, `ghostscript`, or `imagick` |
| `OCR_QUEUE_CONNECTION` / `ocr.queue.connection` | Queue connection for deferred OCR |
| `OCR_QUEUE_NAME` / `ocr.queue.name` | Default queue name |

## Check the setup

```bash
php artisan ocr:check
php artisan ocr:check --lang=eng,ind
php artisan ocr:check --skip-pdf
```

`ocr:check` verifies the configured Tesseract binary, Tesseract 5 version,
requested language data, local temp disk, and PDF backend. Use `--skip-pdf`
when the app only needs image OCR.

## Use image OCR

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

## Use PDF OCR

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

## Use Laravel disks

Call `disk()` when the source path belongs to a configured Laravel filesystem
disk:

```php
$text = Ocr::image('uploads/receipt.png')
    ->disk('s3')
    ->language('eng')
    ->run();
```

Do not set `ocr.temp_disk` to S3 or another remote driver. The temp disk must be
local even when the source disk is remote.

## Queue OCR work

Use `onQueue()` and `dispatch()` for deferred work:

```php
Ocr::pdf('documents/report.pdf')
    ->disk('s3')
    ->language('eng')
    ->onQueue('ocr')
    ->dispatch();
```

Queue name priority is: explicit `onQueue('name')`, then `ocr.queue.name`, then
Laravel's default queue.

## Listen to events

Each OCR invocation emits lifecycle events:

- `Dunn\LaravelOcr\Events\OcrStarted`
- `Dunn\LaravelOcr\Events\OcrCompleted`
- `Dunn\LaravelOcr\Events\OcrFailed`

PDF OCR emits one sequence per page.

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

## Handle expected failures

Catch the package exceptions when presenting user-facing errors:

- `TesseractNotFoundException`: configured binary is missing or not executable.
- `UnsupportedLanguageException`: language code is invalid or traineddata is
  missing from the configured tessdata path.
- `OcrProcessingException`: Tesseract, Ghostscript, or Imagick processing
  failed.

## Troubleshooting

For missing binaries, check:

```bash
which tesseract
tesseract --version
```

For language failures, verify that `{code}.traineddata` exists under the
configured tessdata directory, or install the OS language package such as
`tesseract-ocr-eng`.

For PDF failures, prefer Ghostscript first in CI and server environments:

```ini
OCR_PDF_DRIVER=ghostscript
```

If OCR times out, increase the builder timeout with `timeout(120)` or set
`ocr.timeout`. Large PDFs are affected by page count, DPI, and image complexity.

## Package development checks

When editing this package, run the local checks:

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
