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
     * Initialize
     *
     * @return void
     */
    public function __construct(Database $db, Logger $logger, Async $async, Hook $hook)
    {
        $this->db     = $db;
        $this->logger = $logger;
        $this->async  = $async;
        $this->hook   = $hook;
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
    public function addUser(User $user): bool
    {
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
           '_id' => $user
        ]); 
        
        if ($attributes === null) {
            throw new Exception('user does not exists');
        }

        return new User($attributes);
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
