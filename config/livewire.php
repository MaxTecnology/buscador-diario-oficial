<?php

$maxUploadKb = max(1024, (int) env('LIVEWIRE_UPLOAD_MAX_KB', 102400));
$maxUploadMinutes = max(1, (int) env('LIVEWIRE_UPLOAD_MAX_MINUTES', 15));

return [
    'temporary_file_upload' => [
        'disk' => null,
        'rules' => ['required', 'file', 'max:' . $maxUploadKb],
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => $maxUploadMinutes,
        'cleanup' => true,
    ],
];
