<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\App\Api\Constructor\Http;
use Balloon\App\Api\Hook\Lock;
use Balloon\App\Api\Specifications;
use Balloon\App\Api\v2;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\Hook;
use FastRoute\RouteCollector;

return [
    AbstractBootstrap::class => [
        'calls' => [
            'Balloon.App.Api' => [
                'method' => 'inject',
                'arguments' => ['object' => '{'.Http::class.'}'],
            ],
        ],
    ],
    Hook::class => [
        'calls' => [
            Lock::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Lock::class.'}'],
            ],
        ],
    ],
    RouteCollector::class => [
        'calls' => [[
            'method' => 'addRoute',
            'arguments' => [
                'httpMethod',
                'route',
                'handler',
            ],
            'batch' => [
                ['GET', '/api/v2', [Specifications::class, 'getApiv1']],
                ['GET', '/api/v2/users', [v2\Users::class, 'getAll']],
                ['GET', '/api/v2/users/{user}', [v2\Users::class, 'getOne']],
                ['POST', '/api/v2/users', [v2\Users::class, 'post']],
                ['PUT', '/api/v2/users/{user}', [v2\Users::class, 'put']],
                ['PATCH', '/api/v2/users/{users}', [v2\Users::class, 'patch']],
                ['DELETE', '/api/v2/users/{user}', [v2\Users::class, 'delete']],
                ['GET', '/api/v2/nodes', [v2\Nodes::class, 'getAll']],
                ['GET', '/api/v2/nodes/{node}', [v2\Nodes::class, 'getOne']],
                //['POST', '/api/v2/nodes', [v2\Nodes::class, 'post']],
                //['PUT', '/api/v2/nodes/{node}', [v2\Nodes::class, 'put']],
                //['PATCH', '/api/v2/nodes/{node}', [v2\Nodes::class, 'patch']],
                ['DELETE', '/api/v2/nodes/{node}', [v2\Nodes::class, 'delete']],
                ['GET', '/api/v2/collections', [v2\Collections::class, 'getAll']],
                ['GET', '/api/v2/collections/children', [v2\Collections::class, 'getRootChildren']],
                ['GET', '/api/v2/collections/{collection}', [v2\Collections::class, 'getOne']],
                ['GET', '/api/v2/collections/{collection}/children', [v2\Collections::class, 'getChildren']],
                ['POST', '/api/v2/collections', [v2\Collections::class, 'post']],
                ['PUT', '/api/v2/collections/{collection}', [v2\Collections::class, 'put']],
                ['PATCH', '/api/v2/collections/{collection}', [v2\Collections::class, 'patch']],
                ['DELETE', '/api/v2/collections/{collection}', [v2\Collections::class, 'delete']],
                ['GET', '/api/v2/files', [v2\Files::class, 'getAll']],
                ['GET', '/api/v2/files/{file}', [v2\Files::class, 'getOne']],
                ['POST', '/api/v2/files', [v2\Files::class, 'post']],
                ['PUT', '/api/v2/files/{file}', [v2\Files::class, 'put']],
                ['PATCH', '/api/v2/files/{file}', [v2\Files::class, 'patch']],
                ['DELETE', '/api/v2/files/{file}', [v2\Files::class, 'delete']],
            ],
        ]],
    ],
];
