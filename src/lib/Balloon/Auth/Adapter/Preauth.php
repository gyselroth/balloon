<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter;

use \Balloon\Auth\Exception;
use \Balloon\Auth\Adapter\Basic\Ldap;
use \Balloon\Ldap as LdapServer;
use \Micro\Config;

class Preauth extends Ldap implements AdapterInterface
{
    /**
     * Source networks
     *
     * @var array
     */
    protected $source = [];
    
    
    /**
     * Key
     *
     * @var string
     */
    protected $key;


    /**
     * Set options
     *
     * @param   Iterable $config
     * @return  LdapServer
     */
    public function setOptions(?Iterable $config): LdapServer
    {
        if ($config === null) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'source':
                    $this->source = $value;
                break;
                
                case 'key':
                    if (empty($value)) {
                        throw new Exception('empty preauth key is not allowed');
                    }

                    $this->key = $value;
                break;
            }
        }

        parent::setOptions($config);
        return $this;
    }


    /**
     * Authenticate
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        if (!isset($_SERVER['HTTP_X_PREAUTH'])) {
            $this->logger->debug('skip auth adapter ['.get_class($this).'], no http x-preauth header found', [
                'category' => get_class($this)
            ]);
        
            return false;
        }

        $header = $_SERVER['HTTP_X_PREAUTH'];
        
        if (!empty($header)) {
            $this->logger->debug('found http x-preauth header', [
                'category' => get_class($this)
            ]);
            
            return $this->preauth($header);
        } else {
            $this->logger->warning('http x-preauth header contains an empty empt value', [
                'category' => get_class($this)
            ]);
        
            return false;
        }
    }


    /**
     * Preauth header
     *
     * @param   string $value
     * @return  bool
     */
    public function preauth(string $value): bool
    {
        $parts   = explode('|', $value);

        if (count($parts) !== 2) {
            $this->logger->warning('invalid header x-preauth value, parts != 2', [
                'category' => get_class($this)
            ]);

            return false;
        }

        $found = false;
        foreach ($this->source as $ip) {
            if ($this->ipInRange($_SERVER['REMOTE_ADDR'], $ip)) {
                $this->logger->debug('x-preauth authentication request from known network ['.$_SERVER['REMOTE_ADDR'].']', [
                    'category' => get_class($this)
                ]);

                $found = true;
                break;
            }
        }

        if ($found === false) {
            $this->logger->warning('x-preauth authentication request from unknown network ['.$_SERVER['REMOTE_ADDR'].']', [
                'category' => get_class($this)
            ]);

            return false;
        }

        $key     = $parts[1];
        $account = $parts[0];

        if (!$this->checkLdapUser($account)) {
            return false;
        }

        if ($key !== $this->key) {
            $this->logger->warning('invalid x-preauth key value, wrong key?', [
                'category' => get_class($this)
            ]);
        
            return false;
        } else {
            $this->logger->info('valid x-preauth key value found', [
                'category' => get_class($this)
            ]);
        
            $this->identity = $account;
            return true;
        }
    }


    /**
     * Check if ldap user does exis
     *
     * @param   string $username
     * @return  bool
     */
    public function checkLdapUser(string $username): bool
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

        $this->ldap_dn   = $dn;

        return true;
    }


    /**
     * Check if a given ip is in a network
     *
     * @param   string $ip IP to check in IPV4 format eg. 127.0.0.1
     * @param   string $range IP/CIDR netmask eg. 127.0.0.0/24, also 127.0.0.1 is accepted and /32 assumed
     * @return  bool true if the ip is in this range / false if not.
     */
    protected function ipInRange(string $ip, string $range) : bool
    {
        if (strpos($range, '/') == false) {
            $range .= '/32';
        }

        // $range is in IP/CIDR format eg 127.0.0.1/24
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
}
