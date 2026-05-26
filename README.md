# laravel-ocr

Laravel-first wrapper around Tesseract 5 OCR for images and PDFs.

[![Tests](https://github.com/abduns/laravel-ocr/actions/workflows/tests.yml/badge.svg)](https://github.com/abduns/laravel-ocr/actions)
[![Version](https://img.shields.io/packagist/v/abduns/laravel-ocr.svg)](https://packagist.org/packages/abduns/laravel-ocr)
[![Downloads](https://img.shields.io/packagist/dt/abduns/laravel-ocr.svg)](https://packagist.org/packages/abduns/laravel-ocr)
[![License](https://img.shields.io/packagist/l/abduns/laravel-ocr.svg)](LICENSE)

---

## Features

- Modern PHP support
- Fluent Laravel API for image and PDF OCR
- Queueable OCR jobs
- Lifecycle events for started, completed, and failed runs
- Filesystem disk support, including remote source disks
- Publishable config file
- Artisan command for direct scans
- Setup check command for Tesseract, language data, temp storage, and PDF tooling
- Lazy validation so apps can boot without a local OCR binary
- AI agent instructions included for common coding assistants
- Laravel Boost guidelines and skill included for AI-assisted Laravel development

---

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- Tesseract 5.0+
- For PDF input: Imagick PHP extension or Ghostscript

Install system dependencies first:

```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr tesseract-ocr-eng ghostscript

# macOS with Homebrew
brew install tesseract ghostscript
```

---

## Installation

```bash
composer require abduns/laravel-ocr
```

Publish the config file when you need to customize paths, defaults, queues, or PDF rasterization:

```bash
php artisan vendor:publish --tag=ocr-config
```

The package is auto-discovered by Laravel. The facade alias is `Ocr`.

The Composer package and GitHub repository are `abduns/laravel-ocr`. The PHP namespace intentionally remains `Dunn\LaravelOcr`.

---

## Quick Start

```php
use Dunn\LaravelOcr\Facades\Ocr;

$text = Ocr::image(storage_path('app/invoices/invoice.png'))
    ->language('eng')
    ->run();
```

PDF output is keyed by page number:

```php
$pages = Ocr::pdf(storage_path('app/contracts/contract.pdf'))
    ->language('eng')
    ->runAll();

// [1 => '...', 2 => '...', 3 => '...']
```

---

## Why This Package?

- OCR packages often hide the local binary details that matter in production
- PDF handling usually needs predictable temporary files and cleanup
- Queue and event integration should feel native in Laravel applications

This package focuses on a small, headless Laravel surface around the local Tesseract CLI. It ships no routes, views, frontend assets, cloud OCR SDKs, or language-pack downloaders.

---

## Usage

### Image OCR

```php
use Dunn\LaravelOcr\Facades\Ocr;

$text = Ocr::image(storage_path('app/invoices/invoice.png'))
    ->language('eng')
    ->psm(6)
    ->timeout(30)
    ->run();
```

Multiple languages are supported and passed to Tesseract in order:

```php
$text = Ocr::image($path)
    ->languages(['eng', 'ind'])
    ->run();
```

### PDF OCR

```php
$pages = Ocr::pdf(storage_path('app/contracts/contract.pdf'))
    ->language('eng')
    ->dpi(300)
    ->pages([1, 2, 3])
    ->runAll();
```

If `pages()` is omitted, every page is OCRed in ascending order. Temporary rasterized page images are deleted after success or failure.

### Storage Disks

Use `disk()` when the source lives on a configured Laravel filesystem disk:

```php
$text = Ocr::image('uploads/receipt.png')
    ->disk('s3')
    ->language('eng')
    ->run();
```

Non-local source disks are streamed to the configured local temp disk before OCR.

### Queues

Dispatch a queued OCR job with `onQueue()`:

```php
Ocr::pdf('documents/report.pdf')
    ->disk('s3')
    ->language('eng')
    ->onQueue('ocr')
    ->dispatch();
```

The queue connection comes from `ocr.queue.connection`. The queue name comes from the explicit `onQueue('name')` value, then `ocr.queue.name`, then Laravel's default queue.

### Events

Each OCR invocation dispatches lifecycle events:

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

### Artisan

```bash
php artisan ocr:check
php artisan ocr:check --lang=eng,ind
php artisan ocr:check --skip-pdf
php artisan ocr:scan storage/app/invoice.png --lang=eng --psm=6
php artisan ocr:scan storage/app/report.pdf --lang=eng --dpi=300
```

Use `ocr:check` before wiring OCR into production or queue workers. It verifies the configured Tesseract binary, Tesseract 5 version, requested language data, local temp disk, and PDF backend. Use `--skip-pdf` when the app only needs image OCR.

For PDFs, page output is separated by a form-feed character.

---

## Advanced Usage

### Configuration

Published config lives at `config/ocr.php`.

```php
return [
    'binary' => env('TESSERACT_BIN', '/usr/bin/tesseract'),
    'default_language' => env('OCR_LANG', 'eng'),
    'default_psm' => 3,
    'default_oem' => 3,
    'tessdata_path' => env('TESSDATA_PREFIX'),
    'temp_disk' => 'local',
    'temp_path' => 'ocr/tmp',
    'timeout' => 120,
    'pdf' => [
        'driver' => env('OCR_PDF_DRIVER', 'auto'),
        'default_dpi' => 300,
    ],
    'queue' => [
        'connection' => env('OCR_QUEUE_CONNECTION'),
        'name' => env('OCR_QUEUE_NAME', 'ocr'),
    ],
];
```

`temp_disk` must be a local filesystem disk because Tesseract and the PDF rasterizers need real local file paths.

### Tesseract Options

| Method | Description |
|---|---|
| `language('eng')` | Use one Tesseract language |
| `languages(['eng', 'ind'])` | Use multiple Tesseract languages in order |
| `psm(6)` | Set page segmentation mode, `0..13` |
| `oem(3)` | Set OCR engine mode, `0..3` |
| `timeout(30)` | Override process timeout in seconds |
| `tessdataPath('/path/to/tessdata')` | Override traineddata lookup path |
| `dpi(300)` | Set PDF rasterization DPI |
| `pages([1, 2, 3])` | Limit PDF OCR to selected pages |

### Error Handling

The package exposes typed runtime exceptions:

- `Dunn\LaravelOcr\Exceptions\TesseractNotFoundException`
- `Dunn\LaravelOcr\Exceptions\UnsupportedLanguageException`
- `Dunn\LaravelOcr\Exceptions\OcrProcessingException`

Configuration validation is lazy. The service provider can boot even if Tesseract is missing; failures surface when OCR is executed.

### AI Agent Instructions

This package includes AI coding-agent instructions for common tools:

- `resources/boost/guidelines/core.blade.php` for Laravel Boost package guidelines
- `resources/boost/skills/laravel-ocr-development/SKILL.md` for the Laravel Boost package skill
- `AGENTS.md` for generic repository-wide agent guidance
- `.github/copilot-instructions.md` for GitHub Copilot
- `.cursor/rules/laravel-ocr.mdc` for Cursor
- `.windsurfrules` for Windsurf
- `GEMINI.md` for Gemini
- `CLAUDE.md` and `.claude/skills/laravel-ocr` for Claude Code

Use these when an AI agent needs to add OCR to a Laravel app, configure local Tesseract, wire image or PDF OCR, dispatch queued OCR jobs, listen for lifecycle events, or debug system dependency failures.

In apps using Laravel Boost, the package guidelines and `laravel-ocr-development` skill can be installed by running:

```bash
php artisan boost:install
```

If the package was added after Boost resources were already installed, run:

```bash
php artisan boost:update --discover
```

---

## Standards / Specifications

References:

- https://tesseract-ocr.github.io
- https://github.com/tesseract-ocr/tesseract

---

## Supported Features

| Feature | Support |
|---|---|
| Image OCR | Yes |
| PDF OCR | Yes |
| Multiple Languages | Yes |
| Laravel Filesystem Disks | Yes |
| Queued Jobs | Yes |
| Lifecycle Events | Yes |
| Artisan Scans | Yes |
| Setup Diagnostics | Yes |

---

## Compatibility

| Platform | Supported |
|---|---|
| PHP 8.2+ | Yes |
| Laravel 10.0+ | Yes |
| Tesseract 5.0+ | Yes |
| Symfony Process 6.0+ | Yes |

---

## Design Goals

- Laravel-native API
- Predictable local-process execution
- Explicit configuration
- Safe temporary file handling
- Minimal runtime dependencies
- Strong validation
- Testable internals

---

## Architecture

- Facade-backed `OcrManager`
- Immutable builder state for image and PDF workflows
- Symfony Process execution through the local Tesseract binary
- PDF rasterization through Ghostscript or Imagick
- Laravel queue payload rebuild for deferred OCR jobs
- Events emitted around each OCR operation

---

## Performance

| Operation | Constraint |
|---|---|
| Image OCR | Bound by Tesseract and source image size |
| PDF OCR | Bound by page count, rasterization DPI, and Tesseract |
| Remote disk source | Streamed once to a local temp file |
| Cleanup | Temporary files removed after success or failure |

---

## Testing

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist
vendor/bin/pint --test
```

Real Tesseract integration tests are opt-in:

```bash
OCR_INTEGRATION=1 TESSERACT_BIN=/usr/bin/tesseract vendor/bin/phpunit --testsuite Integration
```

PDF integration also needs Ghostscript or a working Imagick PDF policy.

---

## Roadmap

- [ ] Add optional cloud OCR driver integrations while keeping local Tesseract as the default engine
- [ ] Add more CLI output format options
- [ ] Add per-page PDF progress callbacks
- [ ] Add richer queue result handling examples

---

## Contributing

Contributions, issues, and discussions are welcome.

---

## Security

If you discover security issues, please report them responsibly.

---

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
