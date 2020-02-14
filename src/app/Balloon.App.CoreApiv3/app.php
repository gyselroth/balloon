<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

use Balloon\App\CoreApiv3\Constructor\Http;
use Balloon\App\CoreApiv3\Hook\Lock;
use Balloon\App\CoreApiv3\Specifications;
use Balloon\App\CoreApiv3\v3;
use Balloon\Bootstrap\AbstractBootstrap;
use Balloon\Hook;
use FastRoute\RouteCollector;

return [
    /*Hook::class => [
        'calls' => [
            Lock::class => [
                'method' => 'injectHook',
                'arguments' => ['hook' => '{'.Lock::class.'}'],
            ],
        ],
    ],*/
    RouteCollector::class => [
        'calls' => [[
            'method' => 'addRoute',
            'arguments' => [
                'httpMethod',
                'route',
                'handler',
            ],
            'batch' => [
                ['GET', '/api/v3/users/whoami', [v3\Users::class, 'getWhoami']],
                ['GET', '/api/v3/users', [v3\Users::class, 'getAll']],
                ['GET', '/api/v3/users/{user}', [v3\Users::class, 'getOne']],
                ['POST', '/api/v3/users', [v3\Users::class, 'post']],
                ['PUT', '/api/v3/users/{user}', [v3\Users::class, 'put']],
                ['PATCH', '/api/v3/users/{user}', [v3\Users::class, 'patch']],
                ['DELETE', '/api/v3/users/{user}', [v3\Users::class, 'delete']],
                ['GET', '/api/v3/users/{user}/groups', [v3\Users::class, 'getGroups']],

                ['GET', '/api/v3/groups', [v3\Groups::class, 'getAll']],
                ['GET', '/api/v3/groups/{group}', [v3\Groups::class, 'getOne']],
                ['POST', '/api/v3/groups', [v3\Groups::class, 'post']],
                ['PUT', '/api/v3/groups/{group}', [v3\Groups::class, 'put']],
                ['PATCH', '/api/v3/groups/{group}', [v3\Groups::class, 'patch']],
                ['DELETE', '/api/v3/groups/{group}', [v3\Groups::class, 'delete']],
                ['GET', '/api/v3/groups/{group}/members', [v3\Groups::class, 'getMembers']],

                ['GET', '/api/v3/nodes', [v3\Nodes::class, 'getAll']],
                ['GET', '/api/v3/nodes/{node}', [v3\Nodes::class, 'getOne']],
                ['POST', '/api/v3/nodes', [v3\Nodes::class, 'post']],
                ['PUT', '/api/v3/nodes/{node}', [v3\Nodes::class, 'put']],
                ['PATCH', '/api/v3/nodes/{node}', [v3\Nodes::class, 'patch']],
                ['DELETE', '/api/v3/nodes/{node}', [v3\Nodes::class, 'delete']],
                ['GET', '/api/v3/nodes/{node}/zip', [v3\Nodes::class, 'getZip']],

                ['GET', '/api/v3/collections', [v3\Collections::class, 'getAll']],
                ['GET', '/api/v3/collections/children', [v3\Collections::class, 'getChildren']],
                ['GET', '/api/v3/collections/{collection}', [v3\Collections::class, 'getOne']],
                ['GET', '/api/v3/collections/{collection}/children', [v3\Collections::class, 'getChildren']],

                ['GET', '/api/v3/files', [v3\Files::class, 'getAll']],
                ['GET', '/api/v3/files/{file}', [v3\Files::class, 'getOne']],
                ['GET', '/api/v3/files/{file}/content', [v3\Files::class, 'getContent']],
                ['GET', '/api/v3/files/{file}/versions', [v3\Files::class, 'getVersions']],

                ['GET', '/api/v3/processes', [v3\Processes::class, 'getAll']],
                ['GET', '/api/v3/processes/{process}', [v3\Processes::class, 'getOne']],
                ['DELETE', '/api/v3/processes/{process}', [v3\Processes::class, 'delete']],

                ['GET', '/api/v3/nodes/{node}/processes', [v3\Processes::class, 'getAllNode']],
                ['GET', '/api/v3/nodes/{node}/processes/{process}', [v3\Processes::class, 'getOneNode']],
                ['DELETE', '/api/v3/nodes/{node}/processes/{process}', [v3\Processes::class, 'deleteNode']],

                ['GET', '/api/v3/sessions', [v3\Sessions::class, 'getAll']],
                ['GET', '/api/v3/sessions/{session}', [v3\Sessions::class, 'getOne']],
                ['POST', '/api/v3/sessions', [v3\Sessions::class, 'post']],
                ['PUT', '/api/v3/sessions/{session}', [v3\Sessions::class, 'put']],
                ['PATCH', '/api/v3/sessions/{session}', [v3\Sessions::class, 'patch']],
                ['DELETE', '/api/v3/sessions/{session}', [v3\Sessions::class, 'delete']],

                ['GET', '/api/v3/access-roles', [v3\AccessRoles::class, 'getAll']],
                ['GET', '/api/v3/access-roles/{role}', [v3\AccessRoles::class, 'getOne']],
                ['POST', '/api/v3/access-roles', [v3\AccessRoles::class, 'post']],
                ['PUT', '/api/v3/access-roles/{role}', [v3\AccessRoles::class, 'put']],
                ['PATCH', '/api/v3/access-roles/{role}', [v3\AccessRoles::class, 'patch']],
                ['DELETE', '/api/v3/access-roles/{role}', [v3\AccessRoles::class, 'delete']],

                ['GET', '/api/v3/access-rules', [v3\AccessRules::class, 'getAll']],
                ['GET', '/api/v3/access-rules/{rule}', [v3\AccessRules::class, 'getOne']],
                ['POST', '/api/v3/access-rules', [v3\AccessRules::class, 'post']],
                ['PUT', '/api/v3/access-rules/{rule}', [v3\AccessRules::class, 'put']],
                ['PATCH', '/api/v3/access-rules/{rule}', [v3\AccessRules::class, 'patch']],
                ['DELETE', '/api/v3/access-rules/{rule}', [v3\AccessRules::class, 'delete']],

                ['GET', '/api/v3/events', [v3\Events::class, 'getAll']],
                ['GET', '/api/v3/events/{event}', [v3\Events::class, 'getOne']],

                ['GET', '/api/v3/nodes{node}/events', [v3\Events::class, 'getAllNode']],
                ['GET', '/api/v3/nodes/{node}/events/{event}', [v3\Events::class, 'getOneNode']],
            ],
        ]],
    ],
];
