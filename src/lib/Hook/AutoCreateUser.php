<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Exception;
use Balloon\Server;
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;

class AutoCreateUser extends AbstractHook
{
    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return HookInterface
     */
    public function setOptions(?Iterable $config): HookInterface
    {
        if (null === $config) {
            return $this;
        }

        if (isset($config['attributes'])) {
            $this->attributes = $config['attributes'];
        }

        return $this;
    }

    /**
     * Run: preServerIdentity.
     *
     * @param Server   $server
     * @param Identity $identity
     * @param array    $attributes
     */
    public function preServerIdentity(Server $server, Identity $identity, ?array &$attributes): void
    {
        if (null !== $attributes) {
            return;
        }

        $this->logger->info('found first time username ['.$identity->getIdentifier().'], auto-create user in mongodb user collection', [
             'category' => get_class($this),
        ]);

        $attributes = [
            'username' => $identity->getIdentifier(),
            'created' => new UTCDateTime(),
            'deleted' => false,
        ];

        foreach ($this->attributes as $attr => $value) {
            if (!isset($value['type'])) {
                throw new Exception('to type set for initial user attribute '.$attr);
            }

            if (!isset($value['value'])) {
                throw new Exception('to value set for initial user attribute '.$attr);
            }

            switch ($value['type']) {
                case 'string':
                     $attributes[$attr] = (string) $value['value'];

                break;
                case 'int':
                     $attributes[$attr] = (int) $value['value'];

                break;
                case 'bool':
                     $attributes[$attr] = (bool) $value['value'];

                break;
                case 'binary':
                     $attributes[$attr] = new Binary($value['value']);

                break;
                default:
                    throw new Exception('unknown attribute type '.$value['type'].' for attribute '.$attr.'; use one of [string,int,bool,binary]');
                break;
            }
        }

        $result = $server->getDatabase()->user->insertOne($attributes);
    }
}
