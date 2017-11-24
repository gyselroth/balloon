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
use Micro\Auth\Identity;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
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
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param Database
     * @param LoggerInterface
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

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
    public function preServerIdentity(Identity $identity, ?array &$attributes): void
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

        $result = $this->db->user->insertOne($attributes);
        $attributes['_id'] = $result->getInsertedId();
    }
}
