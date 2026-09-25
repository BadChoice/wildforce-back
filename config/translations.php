<?php

return [
    // https://docs.google.com/spreadsheets/d/1a3H2NRUXxVqaCjwBwMTCbxwFzDeZqlv_8I8C0EZhXho/edit?gid=0#gid=0
    'google_sheet_id' => env('GOOGLE_SHEETS_TRANSLATIONS_ID', '1a3H2NRUXxVqaCjwBwMTCbxwFzDeZqlv_8I8C0EZhXho'),

    'locales' => [
        'en' => ['android_directory' => 'values'],
        'es' => ['android_directory' => 'values-es'],
        'ca' => ['android_directory' => 'values-ca'],
        'de' => ['android_directory' => 'values-de'],
        'fr' => ['android_directory' => 'values-fr'],
        'it' => ['android_directory' => 'values-it'],
        'ja' => ['android_directory' => 'values-ja'],
        'nl' => ['android_directory' => 'values-nl'],
        'pt' => ['android_directory' => 'values-pt'],
        'zh-Hans' => ['android_directory' => 'values-zh-rCN'],
    ],

    'ios_path' => env('TRANSLATIONS_IOS_PATH', '/Users/batcomputer/git/web/wildforce-back'),

    'android_path' => env('TRANSLATIONS_ANDROID_PATH'),
];
