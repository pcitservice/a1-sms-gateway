<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'A1 SMS Gateway API',
            ],
            'routes' => [
                'api'      => 'api/documentation',
                'docs'     => 'docs',
                'oauth2_callback' => 'api/oauth2-callback',
                'middleware' => ['api' => [], 'asset' => [], 'docs' => [], 'oauth2_callback' => []],
                'group_options' => [],
            ],
            'paths' => [
                'docs'        => storage_path('api-docs'),
                'docs_json'   => 'api-docs.json',
                'docs_yaml'   => 'api-docs.yaml',
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),
                'annotations' => [base_path('app/Http/Controllers/Api')],
            ],
            'scanOptions' => [
                'exclude' => [],
                'pattern' => '*.php',
            ],
            'securityDefinitions' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type'   => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
                'security' => [['bearerAuth' => []]],
            ],
            'generate_always'   => env('L5_SWAGGER_GENERATE_ALWAYS', false),
            'generate_yaml_copy' => false,
            'proxy'             => false,
            'additional_config_url' => null,
            'operations_sort'      => null,
            'validator_url'        => null,
            'ui' => [
                'display'    => ['dark_mode' => true, 'doc_expansion' => 'none', 'filter' => true],
                'authorization' => ['persist_authorization' => true],
            ],
            'constants' => [
                'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', env('APP_URL', 'http://localhost').'/api/v1'),
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            'docs_excludes' => [],
        ],
        'paths' => [
            'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),
            'views' => base_path('resources/views/vendor/l5-swagger'),
        ],
    ],
];
