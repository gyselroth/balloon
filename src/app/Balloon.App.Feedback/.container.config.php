<?php
use Balloon\Bootstrap\AbstractBootstrap;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client;
use Balloon\App\Feedback\Constructor\Http;
use Balloon\App\Feedback\Feedback;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Feedback' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}']
            ],
        ]
    ],
    Feedback::class => [
        'services' => [
            ClientInterface::class => [
                'use' => Client::class,
                'arguments' => [
                    'config' => [
                        'base_uri' => '{ENV(BALLOON_FEEDBACK_REMOTE_URL,https://support.gyselroth.net/balloon)}',
                        'connect_timeout' => 3,
                        'timeout' => 60,
                    ]
                ]
            ]
        ]
    ],
];
