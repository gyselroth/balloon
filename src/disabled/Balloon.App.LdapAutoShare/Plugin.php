<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\LdapAutoShare;

use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Ldap;
use Balloon\Plugin\AbstractPlugin;
use Balloon\Plugin\PluginInterface;
use Balloon\User;

class Plugin extends AbstractPlugin
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * shares.
     *
     * @var array
     */
    protected $shares = [];

    /**
     * auto delete.
     *
     * @var bool
     */
    protected $auto_delete = false;

    /**
     * ldap.
     *
     * @var iterable
     */
    protected $ldap;

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return PluginInterface
     */
    public function setOptions(?Iterable $config): PluginInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'shares':
                    $this->shares = $value;

                break;
                case 'auto_delete':
                    $this->{$option} = (bool) (int) $value;

                break;
                case 'ldap':
                    $this->{$option} = $value;

                break;
            }
        }

        return $this;
    }

    /**
     * Execute plugin.
     *
     * @param Filesystem $fs
     */
    public function cli(Filesystem $fs): void
    {
        $this->fs = $fs;

        $shares = [];
        foreach ($this->shares as $name => $share) {
            $this->logger->debug("check auto share [${name}]", [
                'category' => get_class($this),
            ]);

            $result = $this->_findShare($share);
            $shares = array_merge_recursive($shares, $result);
        }

        $this->_syncShare($shares);
    }

    /**
     * Sync share with mongodb.
     *
     * @param array $shares
     */
    protected function _syncShare(array $shares): void
    {
        foreach ($shares as $owner => $folder) {
            $this->logger->debug('sync auto shares for user ['.$owner.']', [
                'category' => get_class($this),
            ]);

            $owner = new User($owner, $this->logger, $this->fs, true);
            $this->fs->setUser($owner);
            $root = $this->fs->getRoot();

            foreach ($folder as $name => $user_share) {
                try {
                    if (Collection::ROOT_FOLDER !== $name) {
                        if (!$root->childExists($name)) {
                            $root->addDirectory($name, [
                                '_plugin' => 'Auto_Share',
                            ]);
                        }

                        $parent = $root->getChild($name);
                    } else {
                        $parent = $root;
                    }
                } catch (\Exception $e) {
                    $this->logger->error("failed add parent share folder [$name]", [
                        'catgory' => get_class($this),
                        'exception' => $e,
                    ]);

                    continue;
                }

                foreach ($user_share as $name => $share) {
                    try {
                        $name = (string) $name;
                        if (!$parent->childExists($name, NodeInterface::DELETED_INCLUDE)) {
                            $parent->addDirectory($name, [
                                '_plugin' => 'Auto_Share',
                            ]);
                        }

                        $node = $parent->getChild($name, NodeInterface::DELETED_INCLUDE);
                        if ($node->isDeleted()) {
                            $node->undelete();
                        }

                        $acl = $node->getAcl();

                        if ($acl !== $share) {
                            $this->logger->debug('acl from exists share does not match ldap sync acl', [
                                'category' => get_class($this),
                            ]);

                            $node->share($share);
                        }
                    } catch (\Exception $e) {
                        $this->logger->error("failed add auto share folder [$name]", [
                            'category' => get_class($this),
                            'exception' => $e,
                        ]);

                        continue;
                    }
                }

                if (true === $this->auto_delete) {
                    $children = $parent->getChildNodes(NodeInterface::DELETED_EXCLUDE, [
                        '_plugin' => 'Auto_Share',
                        'directory' => true,
                        'owner' => $owner->getId(),
                    ]);

                    foreach ($children as $node) {
                        if (!array_key_exists($node->getName(), $user_share)) {
                            $node->delete();
                        }
                    }
                }
            }
        }
    }

    /**
     * Find auto shares from ldap server.
     *
     * @param object $share
     *
     * @return array
     */
    protected function _findShare($share): array
    {
        $owner = (string) $share->share_owner;
        if (empty($owner)) {
            throw new Exception('share owner can not be empty');
        }

        $config = $this->ldap;
        $ldap = (new Ldap($config, $this->logger))->getResource();

        $find = [];
        $name_attr = $share->share_name;
        $acl = $share->acl;

        $subfolder_attr = (string) $share->subfolder_attr;
        if (empty($subfolder_attr)) {
            $sub_folder = Collection::ROOT_FOLDER;
        }

        $result = ldap_search($ldap, (string) $config->basedn, htmlspecialchars_decode((string) $share->filter)/*,$find*/);
        $ldap_shares = [$owner => []];

        if ($result) {
            $data = ldap_get_entries($ldap, $result);
            array_shift($data);

            $this->logger->debug('found ['.count($data).'] ldap objects with auto share filter', [
                'category' => get_class($this),
            ]);

            foreach ($data as $ldap_share) {
                if (!isset($ldap_share[$name_attr]) && !empty($ldap_share[$name_attr])) {
                    $this->logger->info('skip share ['.$ldap_share['dn'].'], share_name attribute ['.$name_attr.'] was not found or is empty', [
                        'category' => get_class($this),
                    ]);

                    continue;
                }
                if (Collection::ROOT_FOLDER !== $subfolder_attr && !isset($ldap_share[$subfolder_attr])) {
                    $this->logger->info('skip share ['.$ldap_share['dn'].'], subfolder attribute ['.$subfolder_attr.'] was not found or is empty', [
                        'category' => get_class($this),
                    ]);

                    continue;
                }

                $share_name = $ldap_share[$name_attr][0];

                if (Collection::ROOT_FOLDER !== $subfolder_attr) {
                    $sub_folder = $ldap_share[$subfolder_attr][0];
                }

                if (!array_key_exists($sub_folder, $ldap_shares[$owner])) {
                    $ldap_shares[$owner][$sub_folder] = [];
                }

                if (!array_key_exists($share_name, $ldap_shares[$owner][$sub_folder])) {
                    $ldap_shares[$owner][$sub_folder][$share_name] = [];
                }

                foreach ($acl as $rule) {
                    $acl_type = $rule->type;
                    $acl_priv = $rule->priv;
                    $acl_role = $rule->role_attr;

                    if (isset($rule['user_attr'])) {
                        $user_attr = $rule->user_attr;
                    } else {
                        $user_attr = $acl_type;
                    }

                    if (!array_key_exists($acl_type, $ldap_shares[$owner][$sub_folder][$share_name])) {
                        $ldap_shares[$owner][$sub_folder][$share_name][$acl_type] = [];
                    }

                    if (!array_key_exists($acl_role, $ldap_share)) {
                        $this->logger->warning('skip share ['.$ldap_share['dn'].'], acl_role attribute ['.$acl_role.'] does not exists', [
                            'category' => get_class($this),
                        ]);

                        continue;
                    }

                    if (is_string($ldap_share[$acl_role])) {
                        $ldap_shares[$owner][$sub_folder][$share_name][$acl_type][] = [
                            $user_attr => $ldap_share[$acl_role],
                            'priv' => $acl_priv,
                        ];
                    } elseif (is_array($ldap_share[$acl_role])) {
                        array_shift($ldap_share[$acl_role]);

                        foreach ($ldap_share[$acl_role] as $attr) {
                            $ldap_shares[$owner][$sub_folder][$share_name][$acl_type][] = [
                                $user_attr => $attr,
                                'priv' => $acl_priv,
                            ];
                        }
                    }
                }
            }

            return $ldap_shares;
        }
        $this->logger->error('failed search for auto shares on ldap server ['.ldap_error($ldap).']', [
                'category' => get_class($this),
            ]);

        return [];
    }
}
