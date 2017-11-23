<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem\Acl\Exception;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

class Acl
{
    /**
     * ACL privileges weight table.
     */
    const PRIVILEGES_WEIGHT = [
        'd' => 0,
        'r' => 1,
        'w' => 2,
        'w+' => 3,
        'rw' => 4,
        'm' => 5,
    ];

    /**
     * Types.
     */
    const TYPE_USER = 'user';
    const TYPE_GROUP = 'group';

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server
     *
     * @var Server
     */
    protected $Server;

    /**
     * Constructor.
     *
     * @param Server $server
     * @param LoggerInterface $logger
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * Check acl.
     *
     * @param NodeInterface $node
     * @param string        $privilege
     *
     * @return bool
     */
    public function isAllowed(NodeInterface $node, string $privilege = 'r'): bool
    {
        $this->logger->debug('check acl for ['.$node->getId().'] with privilege ['.$privilege.']', [
            'category' => get_class($this),
        ]);

        if (null === $node->getFilesystem()->getUser()) {
            $this->logger->debug('system acl call, grant full access', [
                'category' => get_class($this),
            ]);

            return true;
        }

        if (!isset(self::PRIVILEGES_WEIGHT[$privilege])) {
            throw new Exception('unknown privilege '.$privilege.' requested');
        }

        $priv = $this->getAclPrivilege($node);

        $result = false;

        if ('w+' === $priv && $node->isOwnerRequest()) {
            $result = true;
        } elseif ('w+' !== $priv && self::PRIVILEGES_WEIGHT[$priv] >= self::PRIVILEGES_WEIGHT[$privilege]) {
            $result = true;
        }

        $this->logger->debug('check acl for node ['.$node->getId().'], requested privilege ['.$privilege.']', [
            'category' => get_class($this),
            'params' => ['privileges' => $priv],
        ]);

        return $result;
    }

    /**
     * Get access privilege.
     *
     * @param NodeInterface $node
     *
     * @return string
     */
    public function getAclPrivilege(NodeInterface $node): string
    {
        $result = 'd';
        $acl = [];
        $fs = $node->getFilesystem();
        $user = $fs->getUser();

        if ($node->isShareMember()) {
            try {
                $share = $fs->findRawNode($node->getShareId());
            } catch (\Exception $e) {
                $this->logger->error('could not found share node ['.$node->shared.'] for share child node ['.$node->getId().'], dead reference?', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                return 'd';
            }

            if ((string) $share['owner'] === (string) $user->getId()) {
                return 'rw';
            }

            $acl = $share['acl'];
        } elseif ($node->isReference() && $node->isOwnerRequest()) {
            try {
                $share = $fs->findRawNode($node->getShareId());
            } catch (\Exception $e) {
                $this->logger->error('could not found share node ['.$node->getShareId().'] for reference ['.$node->getId().'], dead reference?', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                $node->delete(true);

                return 'd';
            }

            if ($share['deleted'] instanceof UTCDateTime || true !== $share['shared']) {
                $this->logger->error('share node ['.$share['_id'].'] has been deleted, dead reference?', [
                    'category' => get_class($this),
                ]);

                $node->delete(true);

                return 'd';
            }

            $acl = $share['acl'];
        } elseif (!$node->isOwnerRequest()) {
            $this->logger->warning('user ['.$user->getUsername().'] not allowed to access non owned node ['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return 'd';
        } elseif ($node->isOwnerRequest()) {
            return 'm';
        }

        if (!is_array($acl)) {
            return 'd';
        }

        $groups = [];
        foreach ($acl as $rule) {
            if (self::TYPE_USER === $rule['type'] && $rule['id'] === (string) $user->getId()) {
                $priv = $rule['privilege'];
            } elseif (self::TYPE_GROUP === $rule['type'] && in_array($rule['id'], $groups, true)) {
                $priv = $rule['privilege'];
            } else {
                continue;
            }

            if (self::PRIVILEGES_WEIGHT[$priv] > self::PRIVILEGES_WEIGHT[$result]) {
                $result = $priv;
            }
        }

        return $result;
    }

    /**
     * Validate acl.
     *
     * @param array $acl
     *
     * @return bool
     */
    public function validateAcl(array $acl): bool
    {
        if (0 === count($acl)) {
            throw new Exception('there must be at least one acl rule');
        }

        foreach ($acl as $rule) {
            $this->validateRule($rule);
        }

        return true;
    }

    /**
     * Validate rule.
     *
     * @param array $rule
     *
     * @return bool
     */
    public function validateRule(array $rule): bool
    {
        if (!isset($rule['type']) || self::TYPE_USER !== $rule['type'] && self::TYPE_GROUP !== $rule['type']) {
            throw new Exception('rule must contain either a type group or user');
        }

        if (!isset($rule['privilege']) || !isset(self::PRIVILEGES_WEIGHT[$rule['privilege']])) {
            throw new Exception('rule must contain a valid privilege');
        }

        if (!isset($rule['id'])) {
            throw new Exception('rule must contain a resource id');
        }

        $this->verifyRole($rule['type'], new ObjectId($rule['id']));

        return true;
    }


    /**
     * Verify if role exists
     *
     * @param string $type
     * @param string $id
     *
     * @return bool
     */
    protected function verifyRole(string $type, ObjectId $id): bool
    {
        if($type === self::TYPE_USER && $this->server->getUserById($id)) {
            return true;
        } elseif($type === self::TYPE_GROUP && $this->server->getGroupById($id)) {
            return true;
        } else {
            throw new Exception('invalid acl rule resource type');
        }
    }


    /**
     * Get acl with resolved roles

     * @param  array $acl

     * @return array
     */
    public function resolveAclTable(array $acl): array
    {
        foreach($acl as &$rule) {
            try {
                if($rule['type'] === 'user') {
                    $rule['name'] = $this->server->getUserById(new ObjectId($rule['id']))
                        ->getAttribute('name');
                } elseif($rule['type'] === 'group') {
                    $rule['name'] = $this->server->getGroupById(new ObjectId($rule['id']))
                        ->getAttribute('name');
                } else {
                    throw new Exception('invalid acl rule resource type');
                }
            } catch(\Exception $e) {
                $this->logger->error('acl role ['.$rule['id'].'] could not be resolved, remove from list', [
                    'category' => get_class($this),
                    'exception' => $e
                ]);
            }
        }

        return $acl;
    }
}
