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

use \MongoDB\Database;
use \Balloon\Async;
use \Balloon\Hook;
use \Psr\Log\LoggerInterface as Logger;
use \Micro\Auth\Identity;
use \Balloon\Server\User;
use \Balloon\Server\Group;
use \MongoDB\BSON\ObjectId;

class Server
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
     * Hook
     *
     * @var Hook
     */
    protected $hook;

    
    /**
     * Async
     *
     * @var Async
     */
    protected $Async;


    /**
     * Temporary store
     *
     * @var string
     */
    protected $temp_dir = '/tmp/balloon';

    
    /**
     * Max file version
     * 
     * @var int
     */
    protected $max_file_version = 8;

    
    /**
     * Max file size
     * 
     * @var int
     */
    protected $max_file_size = 100000;


    /**
     * Initialize
     *
     * @return void
     */
    public function __construct(Database $db, Logger $logger, Async $async, Hook $hook, ?Iterable $config=null)
    {
        $this->db     = $db;
        $this->logger = $logger;
        $this->async  = $async;
        $this->hook   = $hook;
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param  Iterable $config
     * @return Server
     */
    public function setOptions(?Iterable $config=null): Server
    {
        if($config === null) {
            return $this;
        }

        foreach($config as $name => $value) {
            switch($name) {
                case 'temp_dir':
                    $this->temp_dir = (string)$value;
                break;
                
                case 'max_file_version':
                    $this->max_file_version = (int)$value;
                break;
                
                case 'max_file_size':
                    $this->max_file_size = (int)$value;
                break;
            }
        }

        return $this;
    }

   
    /**
     * Get database
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }
    
    
    /**
     * Get logger
     *
     * @return Logger
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }


    /**
     * Get temporary directory
     *
     * @return string
     */
    public function getTempDir(): string 
    {
        return $this->temp_dir;
    }


    /**
     * Get max file version
     *
     * @return int
     */
    public function getMaxFileVersion(): int
    {
        return $this->max_file_version;
    }


    /**
     * Get max file size
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->max_file_size;
    }


    /**
     * Get hook
     *
     * @return Hook
     */
    public function getHook(): Hook
    {
        return $this->hook;
    }

    
    /**
     * Get async
     *
     * @return Async
     */
    public function getAsync(): Async
    {
        return $this->async;
    }


    /**
     * Set filesystem
     *
     * @param  Filesystem
     * @return Server
     */
    public function setFilesystem(Filesystem $fs): Server
    {
        $this->fs = $fs;
        return $this;
    }


    /**
     * Get Filesystem
     *
     * @return Filesystem
     */
     public function getFilesystem(): Filesystem
     {
        return $this->fs;
     }


    /**
     * Add user
     *
     * @return bool
     */
    public function addUser(array $user): bool
    {
        if($this->userExists($user['username'])) {
            throw new Exception('user does already exists');
        } 
    
        $this->db->user->insertOne($user);       
        return true;
    }

    
    /**
     * Check if user exists
     *
     * @return bool
     */
    public function userExists(string $username): bool
    {
        return $this->db->user->findOne(['username' => $username]) !== null;
    }


    /**
     * Get user by id
     *
     * @param  ObjectId $id
     * @return User
     */
    public function getUserById(ObjectId $id): User
    {
        $attributes = $this->db->user->findOne([
           '_id' => $id
        ]); 
        
        if ($attributes === null) {
            throw new Exception('user does not exists');
        }

        return new User($attributes, $this->fs);
    }

    
    /**
     * Set Identity
     *
     * @param  Identity $identity
     * @return bool
     */
    public function setIdentity(Identity $identity): bool
    {
        $result = $this->db->user->findOne(['username' => $identity->getIdentifier()]);
        //$this->hook->run('preIdentity', [$identity, &$result]);

        if($result === null) {
            throw new Exception('user does not exists');
        } else {
            $user = new User($result, $this->fs);
            $this->user = $user;
            $this->fs->setUser($user);
            $user->updateIdentity($identity);
            return true;
        }
    }   


    /**
     * Get user
     * 
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * Get user by name
     * 
     * @param  string $name
     * @return User
     */
    public function getUserByName(string $name): User
    {
        
    }
        

    /**
     * Get Filesystem
     *
     * @return Filesystem
     */
     public function addGroup(Group $group): Filesystem
     {
     }
} 
