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
                return $this->event_factory->add($args[2], $args[1], ['operation' => $args[1]->isReference() ? 'addCollectionReference' : 'addCollection']);
            case 'collection.factory.postCopy':
                return $this->event_factory->add($args[2], $args[1], ['operation' => 'copyCollection']);
            case 'file.factory.postCopy':
                return $this->event_factory->add($args[2], $args[1], ['operation' => 'copyFile']);
            case 'collection.factory.postShare':
                return $this->event_factory->add($args[2], $args[1], ['operation' => 'shareCollection']);
            case 'collection.factory.postUnshare':
                return $this->event_factory->add($args[2], $args[1], ['operation' => 'unshareCollection']);
            case 'file.factory.postUndelete':
                return $this->event_factory->add($args[1], $args[2], ['operation' => 'undeleteFile']);
            case 'collection.factory.postUndelete':
                $suffix = '';
                if ($args[2]->isReference()) {
                    $suffix = 'Reference';
                } elseif ($args[2]->isShare()) {
                    $suffix = 'Share';
                }

                return $this->event_factory->add($args[1], $args[2], ['operation' => 'undeleteCollection'.$suffix]);
            case 'file.factory.postAdd':
                if($args[4] === true) {
                    return;
                }

                return $this->event_factory->add($args[2], $args[1], ['operation' => 'addFile']);
            case 'collection.factory.postDelete':
                if (false === $args[5]) {
                    return;
                }

                if ($args[2]->isReference()) {
                    if (true === $args[3]) {
                        $operation = 'forceDeleteCollectionReference';
                    } else {
                        $operation = 'deleteCollectionReference';
                    }
                } else {
                    if (true === $args[3]) {
                        $operation = 'forceDeleteCollection';
                    } else {
                        $operation = 'deleteCollection';
                    }
                }

                return $this->event_factory->add($args[2], $args[1], ['operation' => $operation]);
            case 'file.factory.postDelete':
                if (false === $args[5]) {
                    return;
                }

                if (true === $args[3]) {
                    $operation = 'forceDeleteFile';
                } else {
                    $operation = 'deleteFile';
                }

                return $this->event_factory->add($args[2], $args[1], ['operation' => $operation]);


        }
    }
}
