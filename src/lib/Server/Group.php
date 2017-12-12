<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Server;

use Balloon\Server;
use Generator;
use MongoDB\BSON\Binary;
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
     * Last sync timestamp.
     *
     * @var UTCDateTime
     */
    protected $last_attr_sync;

    /**
     * Is group deleted?
     *
     * @var bool
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
     * avatar.
     *
     * @var Binary
     */
    protected $avatar;

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
     *
     * @param array           $attributes
     * @param Server          $server
     * @param Database        $db
     * @param LoggerInterface $logger
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
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Get Attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [
            'id' => $this->_id,
            'name' => $this->name,
            'namespace' => $this->namespace,
            'created' => $this->created,
            'changed' => $this->changed,
            'deleted' => $this->deleted,
            'mail' => $this->mail,
            'avatar' => $this->avatar,
        ];
    }

    /**
     * Get unique id.
     *
     * @return ObjectId
     */
    public function getId(): ObjectId
    {
        return $this->_id;
    }

    /**
     * Save.
     *
     * @param array $attributes
     *
     * @return bool
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
     *
     * @param bool $force
     *
     * @return bool
     */
    public function delete(bool $force = false): bool
    {
    }

    /**
     * Undelete user.
     *
     * @return bool
     */
    public function undelete(): bool
    {
        $this->deleted = false;

        return $this->save(['deleted']);
    }

    /**
     * Check if user is deleted.
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Get Username.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get member.
     *
     * @return array
     */
    public function getMember(): array
    {
        return $this->member;
    }

    public function getResolvedMember(): ?Generator
    {
        foreach ($this->member as $member) {
            yield $this->server->getUserById($member);
        }

        return null;
    }
}
