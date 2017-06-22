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

use \Balloon\Resource\Exception;
use \MongoDB\Database;
use \Psr\Log\LoggerInterface as Logger;

class Resource
{
    /**
     * Load user object with name or with id
     *
     * @param   string|\MongoDB\BSON\ObjectID|Auth $user
     * @param   \MongoDB\Database $db
     * @param   Logger $logger
     * @param   Config $config
     * @param   Plugin $plugin
     * @param   bool $autocreate
     * @param   bool $ignore_deleted
     * @return  void
     */
    public function __construct(User $user, Logger $logger, Filesystem $fs)
    {
        $this->db       = $fs->getDatabase();
        $this->fs       = $fs;
        $this->logger   = $logger;
        $this->user     = $user;
    }

    
    /**
     * Sync user
     *
     * @param  string $q
     * @param  bool $single
     * @return array
     */
    public function searchRole(string $q, bool $single): array
    {
        $users  = $this->searchUser($q, $single);
        $groups = $this->searchGroup($q, $single);
        return array_merge($groups, $users);
    }
   

    /**
     * Sync user
     *
     * @param  string $q
     * @param  bool $single
     * @return array
     */
    public function getUsersByGroup(string $group): array
    {
        $result = $this->db->user->find([
            'groups' => [
                '$elemMatch' => [
                    '$eq' => $group
                ]
            ]
        ], ['username']);

        $list = [];
        foreach ($result as $user) {
            $list[] = new User($user['username'], $this->logger, $this->fs, true, false);
        }
        
        return $list;
    }
    

    /**
     * Sync user
     *
     * @param  string $q
     * @param  bool $single
     * @param  bool $ignore_namespace
     * @return array
     */
    public function searchUser(string $q, bool $single=false, bool $ignore_namespace=false): array
    {
        if (empty($q)) {
            return [];
        }

        if ($single === true) {
            $result = $this->db->user->findOne([
                'username' => $q
            ]);
            
            if (!empty($result)) {
                return [
                    'type' => 'user',
                    'id'   => $q,
                    'name' => $q,
                ];
            }
        }

        $q      = ldap_escape($q);
        $config = $this->user->getAuth()->getAdapter()->getLdapResources();
        if (empty($config)) {
            return [];
        }

        $searchu = $config['user'];
        $ldap    = (new Ldap($config['ldap'], $this->logger))->getResource();

        if ($single == true) {
            $base = $config['ldap']['basedn'];
            $filter = htmlspecialchars_decode(sprintf($searchu['filter_single'], $q));
        } else {
            $ns = $this->user->getAttribute('namespace');
            $filter = htmlspecialchars_decode(sprintf($searchu['filter'], $q));
            $base =  sprintf($config['basedn'], $ns);
        }
        
        $result_user  = ldap_search($ldap, $base, $filter, [
            $searchu['display_attr'],
            $searchu['id_attr'],
        ]);
 
        $filtered = [];

        if ($result_user) {
            $data = ldap_get_entries($ldap, $result_user);
            array_shift($data);

            if ($single && count($data) > 1) {
                throw new Exception('found more than one user with single filter');
            }

            foreach ($data as $role) {
                if (!array_key_exists($searchu['id_attr'], $role) || !array_key_exists($searchu['display_attr'], $role)) {
                    $this->logger->error('failed get user ['.$role['dn'].'], ether ldap attribute ['.$searchu['id_attr'].' or ['.$searchu['display_attr'].'] does not exists', [
                        'category' => get_class($this),
                    ]);
                    
                    continue;
                }

                $filtered[] = [
                    'type' => 'user',
                    'id'   => (is_array($role[$searchu['id_attr']]) ? $role[$searchu['id_attr']][0] : $role[$searchu['id_attr']]),
                    'name' => (is_array($role[$searchu['display_attr']]) ? $role[$searchu['display_attr']][0] : $role[$searchu['display_attr']]),
                ];
            }
        } else {
            $this->logger->error('failed search for users on ldap server ['.ldap_error($ldap).']', [
                'category' => get_class($this),
            ]);
        }
        
        if (empty($filtered)) {
            return [];
        } elseif ($single) {
            return array_shift($filtered);
        } else {
            return $filtered;
        }
    }


    /**
     * Search groups
     *
     * @param  string $q
     * @param  bool $single
     * @return array
     */
    public function searchGroup(string $q, bool $single=false): array
    {
        if (empty($q)) {
            return [];
        }
        

        $q      = ldap_escape($q);
        $config = $this->user->getAuth()->getAdapter()->getLdapResources();
        if (empty($config)) {
            return [];
        }

        $searchg = $config['group'];
        $ldap    = (new Ldap($config['ldap'], $this->logger))->getResource();

        if ($single == true) {
            $filter = htmlspecialchars_decode(sprintf($searchg['filter_single'], $q));
            $base = $config['ldap']['basedn'];
        } else {
            $ns = $this->user->getAttribute('namespace');
            $filter = htmlspecialchars_decode(sprintf($searchg['filter'], $q));
            $base =  sprintf($config['basedn'], $ns);
        }

        $result_group  = ldap_search($ldap, $base, $filter, [
            $searchg['display_attr'],
            $searchg['id_attr'],
        ]);

        $filtered = [];

        if ($result_group) {
            $data = ldap_get_entries($ldap, $result_group);
            array_shift($data);
            
            if ($single && count($data) > 1) {
                throw new Exception('found more than one user with single filter');
            }

            foreach ($data as $role) {
                if (!array_key_exists($searchg['id_attr'], $role) || !array_key_exists($searchg['display_attr'], $role)) {
                    $this->logger->error('failed get group ['.$role['dn'].'], ether ldap attribute ['.$searchg['id_attr'].' or ['.$searchg['display_attr'].'] does not exists', [
                        'category' => get_class($this),
                    ]);
                    
                    continue;
                }
                
                $filtered[] = [
                    'type' => 'group',
                    'id'   => (is_array($role[$searchg['id_attr']]) ? $role[$searchg['id_attr']][0] : $role[$searchg['id_attr']]),
                    'name' => (is_array($role[$searchg['display_attr']]) ? $role[$searchg['display_attr']][0] : $role[$searchg['display_attr']]),
                ];
            }
        } else {
            $this->logger->error('failed search for groups on ldap server ['.ldap_error($ldap).']', [
                'category' => get_class($this),
            ]);
        }

        if (empty($filtered)) {
            return [];
        } elseif ($single) {
            return array_shift($filtered);
        } else {
            return $filtered;
        }
    }
}
