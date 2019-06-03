<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Server;
use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Group implements RoleInterface
{
    /**
     * User unique id.
     *
     * @var ObjectId
     */
    protected $_id;

    /**
     * Name.
     *
     * @var string
     */
    protected $name;

    /**
     * Member.
     *
     * @var array
     */
    protected $member = [];

    /**
     * Is group deleted?
     *
     * @var bool|UTCDateTime
     */
    protected $deleted = false;

    /**
     * Admin.
     *
     * @var bool
     */
    protected $admin = false;

    /**
     * Created.
     *
     * @var UTCDateTime
     */
    protected $created;

    /**
     * Changed.
     *
     * @var UTCDateTime
     */
    protected $changed;

    /**
     * Namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Mail.
     *
     * @var string
     */
    protected $mail;

    /**
     * Db.
     *
     * @var Database
     */
    protected $db;

    /**
     * LoggerInterface.
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
     * Init.
     */
    public function __construct(array $attributes, Server $server, Database $db, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->db = $db;
        $this->logger = $logger;

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }
    }

    /**
     * Return name as string.
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Get Attributes.
     */
    public function getAttributes(): array
    {
        return [
            '_id' => $this->_id,
            'name' => $this->name,
            'namespace' => $this->namespace,
            'created' => $this->created,
            'changed' => $this->changed,
            'deleted' => $this->deleted,
            'member' => $this->member,
        ];
    }

    /**
     * Get unique id.
     */
    public function getId(): ObjectId
    {
        return $this->_id;
    }

    /**
     * Set group attributes.
     */
    public function setAttributes(array $attributes = []): bool
    {
        $attributes = $this->server->validateGroupAttributes($attributes);

        foreach ($attributes as $attr => $value) {
            $this->{$attr} = $value;
        }

        return $this->save(array_keys($attributes));
    }

    /**
     * Save.
     */
    public function save(array $attributes = []): bool
    {
        $set = [];
        foreach ($attributes as $attr) {
            $set[$attr] = $this->{$attr};
        }

        $result = $this->db->group->updateOne([
            '_id' => $this->_id,
        ], [
            '$set' => $set,
        ]);

        return true;
    }

    /**
     * Delete user.
     */
    public function delete(bool $force = false): bool
    {
    }

    /**
     * Undelete user.
     */
    public function undelete(): bool
    {
        $this->deleted = false;

        return $this->save(['deleted']);
    }

    /**
     * Check if user is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deleted instanceof UTCDateTime;
    }

    /**
     * Get Username.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get member.
     */
    public function getMembers(): array
    {
        return $this->member;
    }

    /**
     * Get resolved member.
     *
     * @return Generator
     */
    public function getResolvedMembers(?int $offset = null, ?int $limit = null): ?Generator
    {
        return $this->server->getUsers([
            '_id' => ['$in' => $this->member],
        ], $offset, $limit);
    }
}
