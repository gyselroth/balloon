<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;
use Balloon\Node\Factory as NodeFactory;
use Balloon\User\Factory as UserFactory;

class DeleteNode extends AbstractJob
{
    /**
     * Constructor.
     */
    public function __construct(NodeFactory $node_factory, UserFactory $user_factory, LoggerInterface $logger)
    {
        $this->node_factory = $node_factory;
        $this->user_factory = $user_factory;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $user = $this->user_factory->getOne($this->data['owner']);
        $node = $this->node_factory->getOne($user, $this->data['node']);
        $this->node_factory->delete($user, $node);

        return true;
    }
}
