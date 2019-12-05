<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\EventListener;

use Balloon\Event\Factory as EventFactory;
use League\Event\ListenerInterface;
use League\Event\EventInterface;

class Event implements ListenerInterface
{
    public function __construct(EventFactory $event_factory)
    {
        $this->event_factory = $event_factory;
    }

    public function isListener($listener)
    {
        return $listener === $this;
    }

    public function handle(EventInterface $event)
    {
        $args = func_get_args();

        switch($event->getName()) {
            case 'collection.factory.postAdd':
                $this->event_factory->add($args[2], $args[1], []);
            break;
        }
    }
}
