<?php

return [
    'lang_dir_path' => base_path('lang'),

    'translation_methods' => [
        '__',
        'trans',
        'trans_choice',
        '@lang',
        'Lang::get',
        'Lang::choice',
        'Lang::trans',
        'Lang::transChoice',
        '@choice',
    ],

    'paths' => [app_path(), base_path('ee'), resource_path()],

    'excluded_paths' => [],
];
