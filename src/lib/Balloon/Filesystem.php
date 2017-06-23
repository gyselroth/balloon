<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use \Balloon\Exception;
use \Balloon\User;
use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Plugin;
use \Balloon\Filesystem\Delta;
use \Balloon\Filesystem\Node\INode;
use \Balloon\Filesystem\Node\Collection;
use \Balloon\Filesystem\Node\File;
use \MongoDB\Database;
use \MongoDB\BSON\ObjectID;
use \MongoDB\Model\BSONDocument;
use \Generator;
use \Micro\Config;

class Filesystem
{
    /**
     * Database
     *
     * @var Database
     */
    protected $db;

    
    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;


    /**
     * Plugin
     *
     * @var Plugin
     */
    protected $pluginmgr;

    
    /**
     * Queue
     *
     * @var Queue
     */
    protected $queuemgr;

    
    /**
     * Root collection
     *
     * @var Collection
     */
    protected $root;


    /**
     * User
     *
     * @var Delta
     */
    protected $delta;
    
    
    /**
     * Config
     *
     * @var Config
     */
    protected $config;


    /**
     * Get user
     *
     * @var User
     */
    protected $user;


    /**
     * Initialize
     *
     * @return void
     */
    public function __construct(Database $db, Logger $logger, Config $config, Queue $queuemgr, Plugin $pluginmgr)
    {
        $this->db        = $db;
        $this->logger    = $logger;
        $this->pluginmgr = $pluginmgr;
        $this->config    = $config;
        $this->queuemgr  = $queuemgr;
    }

   
    /**
     * Get user
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
    
    
    /**
     * Set user
     *
     * @param   User $user
     * @return  Filesystem
     */
    public function setUser(User $user): Filesystem
    {
        $this->user = $user;
        return $this;
    }
    

    /**
     * Get database
     *
     * @return Database
     */
    public function getDatabase()
    {
        return $this->db;
    }
    
    
    /**
     * Get Config
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

   
    /**
     * Get logger
     *
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    
    /**
     * Get queue
     *
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queuemgr;
    }


    /**
     * Get plugin
     *
     * @return Plugin
     */
    public function getPlugin()
    {
        return $this->pluginmgr;
    }
    

    /**
     * Get root
     *
     * @return Collection
     */
    public function getRoot()
    {
        if ($this->root instanceof Collection) {
            return $this->root;
        }
 
        return $this->root = new Collection(null, $this);
    }


    /**
     * Get delta
     *
     * @return Delta
     */
     public function getDelta(): Delta
     {
         if ($this->delta instanceof Delta) {
             return $this->delta;
         }
 
         return $this->delta = new Delta($this);
     }

    
    /**
     * Find raw node
     *
     * @param  ObjectID $id
     * @return array
     */
    public function findRawNode(ObjectID $id): array
    {
        $node = $this->db->storage->findOne(['_id' => $id]);
        if ($node === null) {
            throw new Exception\NotFound('node '.$id.' not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        return Helper::convertBSONDocToPhp($node);
    }


    /**
     * Initialize node
     *
     * @param  BSONDocument $node
     * @return INode
     */
    protected function initNode(BSONDocument $node): INode
    {
        if (isset($node['shared']) && $node['shared'] === true && $this->user !== null && $node['owner'] != $this->user->getId()) {
            if (isset($node['reference']) && ($node['reference'] instanceof ObjectId)) {
                $this->logger->debug('reference node ['.$node['_id'].'] requested from share owner, trying to find the shared node', [
                    'category' => get_class($this),
                ]);

                $node = $this->db->storage->findOne([
                    'owner' => $this->user->getId(),
                    'shared' => true,
                    '_id' => $node['reference'],
                ]);

                if ($node === null) {
                    throw new Exception\NotFound('no share node for reference node '.$node['reference'].' found',
                        Exception\NotFound::SHARE_NOT_FOUND
                    );
                }
            } else {
                $this->logger->debug('share node ['.$node['_id'].'] requested from member, trying to find the reference node', [
                    'category' => get_class($this)
                ]);

                $node = $this->db->storage->findOne([
                    'owner' => $this->user->getId(),
                    'shared' => true,
                    'reference' => $node['_id'],
                ]);
                
                if ($node === null) {
                    throw new Exception\NotFound('no share reference for node '.$node['_id'].' found',
                        Exception\NotFound::REFERENCE_NOT_FOUND
                    );
                }
            }
        }

        if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node '.$id.' found, directory attribute does not exists');
        } elseif ($node['directory'] == true) {
            return new Collection($node, $this);
        } else {
            return new File($node, $this);
        }
    }


    /**
     * Factory loader
     *
     * @param   string|ObjectID $id
     * @param   string $class Fore check node type
     * @param   int $deleted
     * @return  INode
     */
    public function findNodeWithId($id, ?string $class=null, int $deleted=INode::DELETED_INCLUDE): INode
    {
        if (!is_string($id) && !($id instanceof ObjectID)) {
            throw new Exception\InvalidArgument($id.' node id has to be a string or instance of \MongoDB\BSON\ObjectID');
        }

        try {
            if (is_string($id)) {
                $id = new ObjectID($id);
            }
        } catch (\Exception $e) {
            throw new Exception\InvalidArgument('invalid node id specified');
        }

        $filter = [
            '_id' => $id
        ];

        switch ($deleted) {
            case INode::DELETED_INCLUDE:
                break;
            case INode::DELETED_EXCLUDE:
                $filter['deleted'] = false;
                break;
            case INode::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];
                break;
        }

        $node = $this->db->storage->findOne($filter);
        
        if ($node === null) {
            throw new Exception\NotFound('node '.$id.' not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }
        
        $return = $this->initNode($node);

        if ($class !== null) {
            $class = '\Balloon\Filesystem\Node\\'.$class;
        }

        if ($class !== null && !($return instanceof $class)) {
            throw new Exception('node '.get_class($return).' is not instance of '.$class);
        }
    
        return $return;
    }


    /**
     * Load node with path
     *
     * @param   string $path
     * @param   string $class Fore check node type
     * @return  INode
     */
    public function findNodeWithPath(string $path='', ?string $class=null): INode
    {
        if (empty($path) || $path[0] != '/') {
            $path = '/'.$path;
        }
        
        $last = strlen($path)-1;
        if ($path[$last] == '/') {
            $path = substr($path, 0, -1);
        }

        $parts = explode('/', $path);
        $parent = new Collection(null, $this);
        array_shift($parts);
        foreach ($parts as $node) {
            $parent = $parent->getChild($node, INode::DELETED_EXCLUDE);
        }
        
        if ($class !== null) {
            $class = '\Balloon\Filesystem\Node\\'.$class;
        }

        if ($class !== null && !($parent instanceof $class)) {
            throw new Exception('node is not instance of '.$class);
        }

        return $parent;
    }

    
    /**
     * Factory loader
     *
     * @param   string $token
     * @param   string $class Fore check node type
     * @return  INode
     */
    public function findNodeWithShareToken(string $token, ?string $class=null): INode
    {
        $node = $this->db->storage->findOne([
            'sharelink.token' => $token,
            'deleted'         => false,
        ]);
        
        if ($node === null) {
            throw new Exception('node with share token '.$token.' not found');
        }

        if ($node['sharelink']['token'] !== $token) {
            throw new Exception('token do not match');
        }
        
        if (isset($node['sharelink']['expiration']) && !empty($node['sharelink']['expiration'])) {
            $time = (int)$node['sharelink']['expiration'];
            if ($time < time()) {
                throw new Exception('share link for this node is expired');
            }
        }

        $return = $this->initNode($node);
        
        if ($class !== null) {
            $class = '\Balloon\Filesystem\Node\\'.$class;
        }

        if ($class !== null && !($return instanceof $class)) {
            throw new Exception('node is not instance of '.$class);
        }

        return $return;
    }
    

    /**
     * Factory loader
     *
     * @param   array $id
     * @param   string $class Fore check node type
     * @param   bool $deleted
     * @return  Generator
     */
    public function findNodes(array $id=[], ?string $class=null, int $deleted=INode::DELETED_INCLUDE): Generator
    {
        $id = (array)$id;

        $find = [];
        foreach ($id as $i) {
            $find[] = new ObjectID($i);
        }
        
        $filter = [
            '_id' => ['$in' => $find]
        ];

        switch ($deleted) {
            case INode::DELETED_INCLUDE:
                break;
            case INode::DELETED_EXCLUDE:
                $filter['deleted'] = false;
                break;
            case INode::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];
                break;
        }
        
        $result = $this->db->storage->find($filter);

        if ($class !== null) {
            $class = '\Balloon\Filesystem\Node\\'.$class;
        }

        $nodes = [];
        foreach ($result as $node) {
            $return = $this->initNode($node);
        
            if ($class !== null && !($return instanceof $class)) {
                throw new Exception('node is not instance of '.$class);
            }
        
            yield $return;
        }
    }


    /**
     * Load node
     *
     * @param   string $id
     * @param   string $path
     * @param   string $class Force set node type
     * @param   bool $deleted
     * @param   bool $multiple Allow $id to be an array
     * @param   bool $allow_root Allow instance of root collection
     * @param   bool $deleted How to handle deleted node
     * @return  INode
     */
    public function getNode($id=null, $path=null, $class=null, $multiple=false, $allow_root=false, $deleted=null)
    {
        if (empty($id) && empty($path)) {
            if ($allow_root === true) {
                return $this->getRoot();
            }
            
            throw new Exception\InvalidArgument('neither parameter id nor p (path) was given');
        } elseif ($id !== null && $path !== null) {
            throw new Exception\InvalidArgument('parameter id and p (path) can not be used at the same time');
        } elseif ($id !== null) {
            if ($deleted === null) {
                $deleted = INode::DELETED_INCLUDE;
            }

            if ($multiple === true && is_array($id)) {
                $node = $this->findNodes($id, $class, $deleted);
            } else {
                $node = $this->findNodeWithId($id, $class, $deleted);
            }
        } elseif ($path !== null) {
            if ($deleted === null) {
                $deleted = INode::DELETED_EXCLUDE;
            }
    
            $node = $this->findNodeWithPath($path, $class);
        }
        
        return $node;
    }
    

    /**
     * Find nodes with custom filters
     *
     * @param   array $filter
     * @return  Generator
     */
    public function findNodesWithCustomFilter(array $filter): Generator
    {
        $result = $this->db->storage->find($filter);
        $list = [];

        foreach ($result as $node) {
            if (!array_key_exists('directory', $node)) {
                continue;
            }
    
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->info('remove node from result list, failed load node', [
                    'category'  => get_class($this),
                    'exception' => $e
                ]);
            }
        }
    }


    /**
     * Get custom filtered children
     *
     * @param   int $deleted
     * @param   array $filter
     * @return  Generator
     */
    public function findNodesWithCustomFilterUser(int $deleted, array $filter): Generator
    {
        if ($this->user instanceof User) {
            $this->user->findNewShares();
        }

        $shares = $this->user->getShares();
        $stored_filter = ['$and' => [
            [],
            ['$or' => [
                ['owner' =>  $this->user->getId()],
                ['shared' => ['$in' => $shares]],
            ]]
        ]];

        if ($deleted === INode::DELETED_EXCLUDE) {
            $stored_filter['$and'][0]['deleted'] = false;
        } elseif ($deleted === INode::DELETED_ONLY) {
            $stored_filter['$and'][0]['deleted'] = ['$type' => 9];
        }
        
        $stored_filter['$and'][0] = array_merge($filter, $stored_filter['$and'][0]);
        $result = $this->db->storage->find($stored_filter);
        
        foreach ($result as $node) {
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->info('remove node from result list, failed load node', [
                    'category'  => get_class($this),
                    'exception' => $e
                ]);
            }
        }
    }


    /**
     * Get children with custom filter
     *
     * @param   array $filter
     * @param   array $attributes
     * @param   int $limit
     * @param   string $cursor
     * @param   bool $has_more
     * @param   INode $parent
     * @return  array
     */
    public function findNodeAttributesWithCustomFilter(
        ?array $filter = null,
        array $attributes = ['_id'],
        ?int $limit = null,
        ?int &$cursor = null,
        ?bool &$has_more = null,
        ?INode $parent = null)
    {
        $default = [
            '_id'       => 1,
            'directory' => 1,
            'shared'    => 1,
            'name'      => 1,
            'parent'    => 1,
        ];

        $search_attributes = array_merge($default, array_fill_keys($attributes, 1));
        $list   = [];
        $result =$this->db->storage->find($filter, [
            'skip'      => $cursor,
            'limit'     => $limit,
            'projection'=> $search_attributes
        ]);
        
        $left =$this->db->storage->count($filter, [
            'skip' => $cursor,
        ]);

        $result   = $result->toArray();
        $count    = count($result);
        $has_more = ($left - $count) > 0;

        foreach ($result as $node) {
            ++$cursor;
            
            try {
                $node = $this->initNode($node);

                if ($parent !== null && !$parent->isSubNode($node)) {
                    continue;
                }
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception'=> $e
                ]);
                continue;
            }

            $values = $node->getAttribute($attributes);
            $list[] = $values;
        }
        
        $has_more = ($left - $count) > 0;
        return $list;
    }
}
