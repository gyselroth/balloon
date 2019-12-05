<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Resource;

use Closure;
use Generator;
use InvalidArgumentException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\ObjectIdInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Log\LoggerInterface;
use League\Event\Emitter;

class Factory
{
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
     * Cache.
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Initialize.
     */
    public function __construct(Emitter $emitter, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->emitter = $emitter;
    }

    /**
     * Add resource.
     */
    public function addTo(Collection $collection, array $resource): array
    {
        $ts = new UTCDateTime();
        $resource += [
            'created' => $ts,
            'changed' => $ts,
            'version' => 1,
        ];

        $this->logger->debug('add new resource to ['.$collection->getCollectionName().']', [
            'category' => get_class($this),
            'resource' => $resource,
        ]);

        $this->emitter->emit('resource.factory.preAdd', ...func_get_args());

        $result = $collection->insertOne($resource);
        $id = $result->getInsertedId();
        $resource['_id'] = $id;

        $this->logger->info('created new resource ['.$id.'] in ['.$collection->getCollectionName().']', [
            'category' => get_class($this),
        ]);

        $this->emitter->emit('resource.factory.postAdd', ...func_get_args()+[$id]);

        return $resource;
    }

    /**
     * Update resource.
     */
    public function updateIn(Collection $collection, ResourceInterface $resource, array $update): bool
    {
        $this->logger->debug('update resource ['.$resource->getId().'] in ['.$collection->getCollectionName().']', [
            'category' => get_class($this),
            'update' => $update,
        ]);
        $this->emitter->emit('resource.factory.preUpdate', ...func_get_args());

        $op = [
            '$set' => $update,
        ];

        /*if (!isset($update['data']) || $resource->getData() === $update['data']) {
            $this->logger->info('resource ['.$resource->getId().'] version ['.$resource->getVersion().'] in ['.$collection->getCollectionName().'] is already up2date', [
                'category' => get_class($this),
            ]);
        } else {
            $this->logger->info('add new history record for resource ['.$resource->getId().'] in ['.$collection->getCollectionName().']', [
                'category' => get_class($this),
            ]);

            $op['$set']['changed'] = new UTCDateTime();
            $op += [
                '$addToSet' => ['history' => array_intersect_key($resource->toArray(), array_flip(['data', 'version', 'changed', 'description']))],
                '$inc' => ['version' => 1],
            ];
        }*/

        $result = $collection->updateOne(['_id' => $resource->getId()], $op);

        $this->logger->info('updated resource ['.$resource->getId().'] in ['.$collection->getCollectionName().']', [
            'category' => get_class($this),
        ]);

        $this->emitter->emit('resource.factory.postUpdate', ...func_get_args());

        return true;
    }

    /**
     * Delete resource.
     */
    public function deleteFrom(Collection $collection, ObjectIdInterface $id): bool
    {
        $this->logger->info('delete resource ['.$id.'] from ['.$collection->getCollectionName().']', [
            'category' => get_class($this),
        ]);

        $this->emitter->emit('resource.factory.preDelete', ...func_get_args());
        $result = $collection->deleteOne(['_id' => $id]);
        $this->emitter->emit('resource.factory.postDelete', ...func_get_args());

        return true;
    }

    /**
     * Get all.
     */
    public function getAllFrom(Collection $collection, ?array $query = null, ?int $offset = null, ?int $limit = null, ?array $sort = null, ?Closure $build = null): Generator
    {
        $total = $collection->count($query);
        $offset = $this->calcOffset($total, $offset);

        if (empty($sort)) {
            $sort = ['$natural' => -1];
        }

        $result = $collection->find($query, [
            'projection' => ['history' => 0],
            'skip' => $offset,
            'limit' => $limit,
            'sort' => $sort,
        ]);

        foreach ($result as $resource) {
            $result = $build->call($this, $resource);
            if ($result !== null) {
                yield (string) $resource['_id'] => $result;
            }
        }

        return $total;
    }

    /**
     * Change stream.
     */
    public function watchFrom(Collection $collection, ?ObjectIdInterface $after = null, bool $existing = true, ?array $query = [], ?Closure $build = null, ?int $offset = null, ?int $limit = null, ?array $sort = null): Generator
    {
        $pipeline = $query;
        if (!empty($pipeline)) {
            $pipeline = [['$match' => $this->prepareQuery($query)]];
        }

        $stream = $collection->watch($pipeline, [
            'resumeAfter' => $after,
            'fullDocument' => 'updateLookup',
        ]);

        if ($existing === true) {
            $total = $collection->count($query);

            if ($offset !== null && $total === 0) {
                $offset = null;
            } elseif ($offset < 0 && $total >= $offset * -1) {
                $offset = $total + $offset;
            }

            $result = $collection->find($query, [
                'projection' => ['history' => 0],
                'skip' => $offset,
                'limit' => $limit,
                'sort' => $sort,
            ]);

            foreach ($result as $resource) {
                $bound = $build->call($this, $resource);

                if ($bound === null) {
                    continue;
                }

                yield (string) $resource['_id'] => [
                    'insert',
                    $bound,
                ];
            }
        }

        for ($stream->rewind(); true; $stream->next()) {
            if (!$stream->valid()) {
                continue;
            }

            $event = $stream->current();
            $bound = $build->call($this, $event['fullDocument']);

            if ($bound === null) {
                continue;
            }

            yield (string) $event['fullDocument']['_id'] => [
                $event['operationType'],
                $bound,
            ];
        }
    }

    /**
     * Build.
     */
    public function initResource(ResourceInterface $resource)
    {
        $this->logger->debug('initialized resource ['.$resource->getId().'] as ['.get_class($resource).']', [
            'category' => get_class($this),
        ]);

        $this->emitter->emit('resource.factory.onInit', ...func_get_args());

        return $resource;
    }

    /**
     * Add fullDocument prefix to keys.
     */
    protected function prepareQuery(array $query): array
    {
        $new = [];
        foreach ($query as $key => $value) {
            switch ($key) {
                case '$and':
                case '$or':
                    foreach ($value as $sub_key => $sub) {
                        $new[$key][$sub_key] = $this->prepareQuery($sub);
                    }

                break;
                default:
                    $new['fullDocument.'.$key] = $value;
            }
        }

        return $new;
    }

    /**
     * Calculate offset.
     */
    protected function calcOffset(int $total, ?int $offset = null): ?int
    {
        if ($offset !== null && $total === 0) {
            $offset = 0;
        } elseif ($offset < 0 && $total >= $offset * -1) {
            $offset = $total + $offset;
        } elseif ($offset < 0) {
            $offset = 0;
        }

        return $offset;
    }
}
