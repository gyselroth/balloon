<?php
use Balloon\Hook;
use Balloon\App\Recaptcha\Hook\Recaptcha;
use ReCaptcha\ReCaptcha as CaptchaService;

return [
    'Apps' => [
        'Balloon.App.Recaptcha' => [
            'enabled' => false
        ]
    ],
    Hook::class => [
        'calls' => [
            Recaptcha::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Recaptcha::class.'}']
            ],
        ],
    ],
    CaptchaService::class => [
        'arguments' => [
            'secret' => '{ENV(BALLOON_RECAPTCHA_SECRET)}'
        ]
    ]
];
