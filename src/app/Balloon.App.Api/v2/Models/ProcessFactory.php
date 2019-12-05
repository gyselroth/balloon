<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Api\v2\Models;

use Balloon\AttributeDecorator\AttributeDecoratorInterface;
use Balloon\Resource\ResourceInterface;
use Balloon\Rest\ModelFactoryInterface;
use Balloon\Node\Factory as NodeFactory;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use TaskScheduler\JobInterface;
use DateTime;

class ProcessFactory extends AbstractModelFactory
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Hook.
     *
     * @var Hook
     */
    //protected $hook;

    public function __construct(NodeFactory $node_factory)
    {
        $this->node_factory = $node_factory;
    }


    /**
     * Get process Attributes.
     */
    protected function getAttributes(ResourceInterface $process, ServerRequestInterface $request): array
    {
        $resource = $process->toArray();


/*        $quota = null;

        $data = $this->getData();
        $parent = isset($data['parent']) ? (string) $data['parent'] : null;
        $job = isset($data['job']) ? $data['job'] : null;
        unset($data['parent'], $data['namespace'], $data['job']);
 */

        //var_dump($resource);
        return [
            'changed' => $resource['created'] === 0 ? null : (new DateTime('@'.(string) $resource['created']))->format('c'),
            'job' => $resource['class'],
            'node' => (string)$resource['data']['node'] ?? null,
            'status' => [
                'next' => $resource['options']['at'] === 0 ? null : (new DateTime('@'.(string) $resource['options']['at']))->format('c'),
                'started' => $resource['status'] === 0 ? null : $resource['started']->toDateTime()->format('c'),
                'ended' => $resource['status'] <= 2 ? null : $resource['ended']->toDateTime()->format('c'),
                'progress' => $resource['progress'] ?? null,
                'result' => JobInterface::STATUS_MAP[$resource['status']],
                'code' => $resource['status'],
            ],
        ];
    }
}

