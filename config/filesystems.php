<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'owner' => [
            'driver' => 'local',
            // 'root' => public_path(config('storage.owner.image.template')),
            // 'url' => env('APP_URL').config('storage.owner.image.template'),
            'root' => public_path('storage/owner/image/template'),
            'url' => env('APP_URL').'/storage/owner/image/template',
            'visibility' => 'public',
            'throw' => false,
        ],

        'greeting' => [
            'driver' => 'local',
            // 'root' => public_path(config('storage.owner.image.template')),
            // 'url' => env('APP_URL').config('storage.owner.image.template'),
            'root' => public_path('storage/owner/image/greeting'),
            'url' => env('APP_URL').'/storage/owner/image/greeting',
            'visibility' => 'public',
            'throw' => false,
        ],

        'garbage' => [
            'driver' => 'local',
            'root' => storage_path('_garbage'),
            // 'url' => env('APP_URL').'/storage/_garbage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // 'public' => [
        //     'driver' => 'local',
        //     // 'root' => storage_path('app/public'),
        //     'root' => public_path('storage'),
        //     'url' => env('APP_URL').'/storage',
        //     'visibility' => 'public',
        //     'throw' => false,
        // ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
