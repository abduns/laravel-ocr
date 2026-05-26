<?php

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
