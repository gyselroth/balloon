<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Exception;
use Balloon\Server;
use Balloon\Server\User;
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use Psr\Log\LoggerInterface;

class AutoCreateUser extends AbstractHook
{
    /**
     * Attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger, ?array $config = null)
    {
        $this->server = $server;
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     */
    public function setOptions(?iterable $config): HookInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'attributes':
                    $this->attributes = $value;

                break;
                default:
                    throw new Exception('Invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function preServerIdentity(Identity $identity, ?User &$user): void
    {
        if (null !== $user) {
            return;
        }

        $this->logger->info('found first time username ['.$identity->getIdentifier().'], auto-create user', [
             'category' => static::class,
        ]);

        $attributes = [];

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

        $id = $this->server->addUser($identity->getIdentifier(), $attributes);
        $user = $this->server->getUserById($id);
    }
}
