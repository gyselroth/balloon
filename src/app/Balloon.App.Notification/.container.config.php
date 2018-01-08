<?php
use Balloon\Hook;
use Balloon\App\Notification\Hook\NewShareAdded;

return [
    'service' => [
        Hook::class => [
            'adapter' => [
                NewShareAdded::class => []
            ],
        ],
    ]
];
