<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office;

use Balloon\App\Office\Session\Member;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class Session
{
    /**
     * Document.
     *
     * @var Document
     */
    protected $document;

    /**
     * Valid until.
     *
     * @var UTCDateTime
     */
    protected $ttl;

    /**
     * User (Session owner).
     *
     * @var User
     */
    protected $user;

    /**
     * Session members.
     *
     * @var array
     */
    protected $member = [];

    /**
     * Session id.
     *
     * @var ObjectId
     */
    protected $_id;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Session.
     *
     * @param Filesystem $fs
     * @param Document   $document
     * @param int        $ttl
     * @param array      $session
     */
    public function __construct(Filesystem $fs, Document $document, int $ttl = 3600, $session = [])
    {
        $this->user = $fs->getUser();
        $this->fs = $fs;
        $this->db = $fs->getDatabase();

        $this->document = $document;

        if (empty($session)) {
            $ts = (time() + $ttl) * 1000;
            $this->ttl = new UTCDateTime($ts);
        } else {
            foreach ($session as $attribute => $value) {
                $this->{$attribute} = $value;
            }
        }

        $this->user = $fs->getUser();
        $this->document = $document;
    }

    /**
     * Get document.
     *
     * @return Document
     */
    public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * Get member.
     *
     * @retrun Iterable
     */
    public function getMember(): Iterable
    {
        return $this->member;
    }

    /**
     * Get user.
     *
     * @retrun User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Get valid until.
     *
     * @return UTCDateTime
     */
    public function getTTL(): UTCDateTime
    {
        return $this->ttl;
    }

    /**
     * Get Session.
     *
     * @param Server   $server
     * @param ObjectId $session_id
     * @param string   $access_token
     *
     * @return Session
     */
    public static function getByAccessToken(Server $server, ObjectId $session_id, string $access_token): self
    {
        $result = $server->getDatabase()->app_office_session->findOne([
            '_id' => $session_id,
            'member' => [
                '$elemMatch' => [
                    'access_token' => $access_token,
                    'ttl' => [
                        '$gte' => new UTCDateTime(),
                    ],
                ],
            ],
            'ttl' => [
                '$gte' => new UTCDateTime(),
            ],
        ]);

        if (null === $result) {
            throw new Exception('access_token is invalid or expired');
        }

        foreach ($result['member'] as $member) {
            if ($member['access_token'] === $access_token) {
                $user = $server->getUserById($member['user']);
                $fs = $user->getFilesystem();
                $node = $fs->findNodeWithId($result['node'], 'File');
                $document = new Document($fs->getDatabase(), $node);

                return new self($fs, $document, 0, $result);
            }
        }
    }

    /**
     * Get session by id.
     *
     * @param Filesystem $fs
     * @param ObjectId   $session_id
     *
     * @return Session
     */
    public static function getSessionById(Filesystem $fs, ObjectId $session_id): self
    {
        $result = $fs->getDatabase()->app_office_session->findOne([
            '_id' => $session_id,
            'ttl' => [
                '$gte' => new UTCDateTime(),
            ],
        ]);

        if (null === $result) {
            throw new Exception('session does not exists');
        }

        $node = $fs->findNodeWithId($result['node'], File::class);
        $document = new Document($fs->getDatabase(), $node);

        return new self($fs, $document, 0, $result);
    }

    /**
     * Destroy entire session.
     *
     * @return bool
     */
    public function destroy(): bool
    {
        $this->db->app_office_session->deleteOne([
            '_id' => $this->_id,
        ]);

        return true;
    }

    /**
     * Remove member from session.
     *
     * @param User $user
     *
     * @return Session
     */
    public function leave(User $user): self
    {
        foreach ($this->member as $key => $member) {
            if ($member['user'] === $user->getId()) {
                unset($this->member[$key]);
            }
        }

        return $this;
    }

    /**
     * Join member.
     *
     * @param Member $member
     *
     * @return Session
     */
    public function join(Member $member): self
    {
        $member->setSession($this);
        $this->member[] = $member;

        return $this;
    }

    /**
     * Save session.
     *
     * @return Session
     */
    public function store(): self
    {
        if (null === $this->_id) {
            $data = [
                'ttl' => $this->ttl,
                'user' => $this->user->getId(),
                'node' => $this->document->getNode()->getId(),
                'member' => $this->member,
            ];

            $result = $this->db->app_office_session->insertOne($data);
            $this->_id = $result->getInsertedId();
        } else {
            if (0 === count($this->member)) {
                $this->db->app_office_session->deleteOne(['_id' => $this->_id]);
            } else {
                $this->db->app_office_session->updateOne(['_id' => $this->_id], ['$set' => ['member' => $this->member]]);
            }
        }

        return $this;
    }

    /**
     * Get session id.
     *
     * @return ObjectId
     */
    public function getId(): ObjectId
    {
        return $this->_id;
    }
}
