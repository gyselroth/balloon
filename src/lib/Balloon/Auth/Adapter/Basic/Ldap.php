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

use Balloon\Auth\Exception;
use \Psr\Log\LoggerInterface as Logger;
use Balloon\Config;
use Balloon\Ldap as LdapServer;
use Balloon\Auth\Adapter\AdapterInterface;

class Ldap extends LdapServer implements AdapterInterface
{
    /**
     * Identity
     *
     * @var string
     */
    protected $identity;


    /**
     * LDAP DN
     *
     * @var string
     */
    protected $ldap_dn;


    /**
     * attribute sync cache
     *
     * @var int
     */
    protected $attr_sync_cache = 0;
    
    
    /**
     * attribute map
     *
     * @var Iterable
     */
    protected $map = [];
   

    /**
     * my account filter
     *
     * @var string
     */
    protected $account_filter = '(uid=%s)';
    

    /**
     * ldap resources
     *
     * @var Iterable
     */
    protected $ldap_resources = [];


    /**
     * Ldap connect
     *
     * @param   Iterable $config
     * @param   Logger $logger
     * @return  void
     */
    public function __construct(?Iterable $config, Logger $logger)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }
    

    /**
     * Get search
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
        return $this->attr_sync_cache;
    }


    /**
     * Set options
     *
     * @param   Iterable
     * @return  Ldap
     */
    public function setOptions(?Iterable $config): LdapServer
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'ldap':
                    parent::setOptions($value);
                break;

                case 'map':
                    $this->map = $value;
                break;
                
                case 'account_filter':
                    $this->account_filter = $value;
                break;
                
                case 'ldap_resources':
                    $this->ldap_resources = $value;
                break;
                
                case 'attr_sync_cache':
                    $this->attr_sync_cache = (int)$value;
                break;
            }
        }
        
        return $this;
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
     * LDAP Auth
     *
     * @param   string $username
     * @param   string $password
     * @return  bool
     */
    public function plainAuth(string $username, string $password): bool
    {
        $this->connect();
        $esc_username = ldap_escape($username);
        $filter       = sprintf($this->account_filter, $esc_username);
        $result       = ldap_search($this->connection, $this->basedn, $filter, ['dn']);
        $entries      = ldap_get_entries($this->connection, $result);

        if ($entries['count'] === 0) {
            $this->logger->warning("user not found with ldap filter [{$filter}]", [
                'category' => get_class($this)
            ]);

            return false;
        } elseif ($entries['count'] > 1) {
            $this->logger->warning("more than one user found with ldap filter [{$filter}]", [
                'category' => get_class($this)
            ]);

            return false;
        }

        $dn = $entries[0]['dn'];
        $this->logger->info("found ldap user [{$dn}] with filter [{$filter}]", [
            'category' => get_class($this)
        ]);

        $result = ldap_bind($this->connection, $dn, $password);
        $this->logger->info("bind ldap user [{$dn}]", [
            'category' => get_class($this),
            'result'   => $result
        ]);

        if ($result === false) {
            return false;
        }

        $this->identity  = $username;
        $this->ldap_dn   = $dn;

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
     * Get attribute map
     *
     * @return Iterable
     */
    public function getAttributeMap(): Iterable
    {
        return $this->map;
    }

    
    /**
     * Get attributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $search = [];
        foreach ($this->map as $attr => $value) {
            $search[] = $value['attr'];
        }

        $result     = ldap_read($this->connection, $this->ldap_dn, '(objectClass=*)', $search);
        $entries    = ldap_get_entries($this->connection, $result);
        $attributes = $entries[0];

        $this->logger->info("get ldap user [{$this->ldap_dn}] attributes", [
            'category' => get_class($this),
            'params'   => $attributes,
        ]);

        return $attributes;
    }
}
