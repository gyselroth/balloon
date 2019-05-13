<?php
use Balloon\Hook;
use Balloon\App\Recaptcha\Hook\Recaptcha;
use ReCaptcha\ReCaptcha as CaptchaService;

return [
    Hook::class => [
        'calls' => [
            Recaptcha::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Recaptcha::class.'}']
            ],
        ],
    ]
];
