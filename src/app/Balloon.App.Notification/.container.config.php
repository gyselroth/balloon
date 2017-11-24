<?php
use Balloon\Hook;
use Balloon\App\Notification\Hook\NewShareAdded;

return [
    Hook::class => [
        'adapter' => [
            NewShareAdded::class => []
        ],
    ],
];
