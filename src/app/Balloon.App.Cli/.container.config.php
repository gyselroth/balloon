<?php
use Balloon\Bootstrap\Cli as CliBootstrap;
use Balloon\App\Cli\Constructor\Cli;

return [
    CliBootstrap::class => [
        'calls' => [
            'Balloon.App.Cli' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Cli::class.'}']
            ],
        ]
    ],
];
