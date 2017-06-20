<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter\Basic;

use \Psr\Log\LoggerInterface as Logger;
use Balloon\Config;
use Balloon\Auth\Adapter\AdapterInterface;
use \MongoDB\Database;

class Db implements AdapterInterface
{
    /**
     * Identity
     *
     * @var string
     */
    protected $identity;


    /**
     * Logger
     *
     * @var Logger
     */
    protected $logger;

    
    /**
     * ldap resources
     *
     * @var Iterable
     */
    protected $ldap_resources = [];

    
    /**
     * Db
     *
     * @var Database
     */
    protected $db;


    /**
     * Ldap connect
     *
     * @param   Iterable $config
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(?Iterable $config, Logger $logger)
    {
        $this->logger  = $logger;
        $this->setOptions($config);
    }


    /**
     * Set options
     *
     * @param   Iterable
     * @return  Ldap
     */
    public function setOptions(?Iterable $config): Db
    {
        if ($config === null) {
            return $this;
        }
    
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'ldap_resources':
                    $this->ldap_resources = $value;
                break;
            }
        }

        return $this;
    }
    

    /**
     * Set database
     *
     * @param Database $db
     * @return Db
     */
    public function setDatabase(Database $db): Db
    {
        $this->db = $db;
        return $this;
    }

    
    /**
     * Get ldap resources
     *
     * @return Iterable
     */
    public function getLdapResources(): Iterable
    {
        return $this->ldap_resources;
    }

      
    /**
     * Get attribute sync cache
     *
     * @return int
     */
    public function getAttributeSyncCache(): int
    {
        return -1;
    }


    /**
     * Authenticate
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $this->logger->debug('skip auth adapter ['.get_class($this).'], no http authorization header found', [
                'category' => get_class($this)
            ]);
        
            return false;
        }

        $header = $_SERVER['HTTP_AUTHORIZATION'];
        $parts  = explode(' ', $header);
        
        if ($parts[0] == 'Basic') {
            $this->logger->debug('found http basic authorization header', [
                'category' => get_class($this)
            ]);

            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];

            return $this->plainAuth($username, $password);
        } else {
            $this->logger->warning('http authorization header contains no basic string or invalid authentication string', [
                'category' => get_class($this)
            ]);
        
            return false;
        }
    }


    /**
     * Auth
     *
     * @param   string $username
     * @param   string $password
     * @return  bool
     */
    public function plainAuth(string $username, string $password): bool
    {
        $result = $this->db->user->findOne([
            'username' => $username
        ]);

        if ($result === null) {
            $this->logger->info('found no user named ['.$username.'] in database', [
                'category' => get_class($this)
            ]);

            return false;
        }
        
        if (!isset($result['password']) || empty($result['password'])) {
            $this->logger->info('found no password for ['.$username.'] in database', [
                'category' => get_class($this)
            ]);
         
            return false;
        }

        if (!password_verify($password, $result['password'])) {
            $this->logger->info('failed match given password for ['.$username.'] with stored hash in database', [
                'category' => get_class($this)
            ]);
         
            return false;
        }

        $this->identity  = $username;
        return true;
    }


    /**
     * Get identity
     *
     * @return string
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    
    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [];
    }
}
