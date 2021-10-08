<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Session;

use Balloon\Filesystem\Node\Collection;
use Balloon\Server\User;
use Balloon\Session;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;

class Factory
{
    /**
     * Collection name.
     */
    public const COLLECTION_NAME = 'sessions';

    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Initialize.
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Prepare query.
     */
    public function prepareQuery(User $user, ?array $query = null): array
    {
        $filter = [
            'owner' => $user->getId(),
        ];

        if (!empty($query)) {
            $filter = [
                '$and' => [$filter, $query],
            ];
        }

        return $filter;
    }

    /**
     * Get session.
     */
    public function getOne(User $user, ObjectIdInterface $id): SessionInterface
    {
        $result = $this->db->{self::COLLECTION_NAME}->findOne([
            'owner' => $user->getId(),
            '_id' => $id,
        ]);

        if ($result === null) {
            throw new Exception\NotFound('session '.$id.' is not registered');
        }

        return $this->build($result);
    }

    /**
     * Delete by name.
     */
    public function deleteOne(ObjectIdInterface $id): bool
    {
        $this->db->{self::COLLECTION_NAME}->deleteOne([
            '_id' => $id,
        ]);

        return true;
    }

    /**
     * Update.
     */
    public function update(User $user, SessionInterface $resource, Collection $parent, $stream): bool
    {
        if ($resource->isFinalized()) {
            throw new Exception\Closed('This session has been finalized and can not be updated.');
        }

        $ctx = $resource->getHashContext();
        $size = md5_update_stream($ctx, $stream);
        rewind($stream);

        $storage = $parent->getStorage();
        $session = $storage->storeTemporaryFile($stream, $user, $resource->getId());
        $size = $resource->getSize() + $size;

        $data = [
            '$set' => [
                'size' => $size,
                'changed' => new UTCDateTime(),
            ],
        ];

        if ($size % 64 !== 0) {
            $data['$set']['hash'] = md5_final($ctx);
        } else {
            $data['$set']['context'] = serialize($ctx);
        }

        $resource->set(array_merge(['size' => $size], $data['$set']));
        $this->db->{self::COLLECTION_NAME}->updateOne([
            '_id' => $resource->getId(),
            'owner' => $user->getId(),
        ], $data);

        return true;
    }

    /**
     * Add session.
     */
    public function add(User $user, Collection $parent, $stream): SessionInterface
    {
        $ctx = md5_init();
        $size = md5_update_stream($ctx, $stream);
        rewind($stream);

        $storage = $parent->getStorage();
        $session = $storage->storeTemporaryFile($stream, $user);

        $resource = [
            '_id' => $session,
            'kind' => 'Session',
            'created' => new UTCDateTime(),
            'changed' => new UTCDateTime(),
            'parent' => $parent->getId(),
            'size' => $size,
            'owner' => $user->getId(),
        ];

        if ($size % 64 !== 0) {
            $resource['hash'] = md5_final($ctx);
        } else {
            $resource['context'] = serialize($ctx);
        }

        $this->db->{self::COLLECTION_NAME}->insertOne($resource);

        return $this->build($resource);
    }

    /**
     * Build instance.
     */
    public function build(array $resource): SessionInterface
    {
        return new Session($resource);
    }
}
