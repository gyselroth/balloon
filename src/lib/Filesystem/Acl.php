<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem;

use Balloon\Filesystem\Acl\Exception;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

class Acl
{
    /**
     * Privileges.
     */
    public const PRIVILEGE_DENY = 'd';
    public const PRIVILEGE_READ = 'r';
    public const PRIVILEGE_WRITE = 'w';
    public const PRIVILEGE_WRITEPLUS = 'w+';
    public const PRIVILEGE_READWRITE = 'rw';
    public const PRIVILEGE_MANAGE = 'm';

    /**
     * ACL privileges weight table.
     */
    public const PRIVILEGES_WEIGHT = [
        self::PRIVILEGE_DENY => 0,
        self::PRIVILEGE_READ => 1,
        self::PRIVILEGE_WRITE => 2,
        self::PRIVILEGE_WRITEPLUS => 3,
        self::PRIVILEGE_READWRITE => 4,
        self::PRIVILEGE_MANAGE => 5,
    ];

    /**
     * Types.
     */
    public const TYPE_USER = 'user';
    public const TYPE_GROUP = 'group';

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Check acl.
     */
    public function isAllowed(NodeInterface $node, string $privilege = self::PRIVILEGE_READ, ?User $user = null): bool
    {
        $this->logger->debug('check acl for ['.$node->getId().'] with privilege ['.$privilege.']', [
            'category' => get_class($this),
        ]);

        $custom = $user;
        $user = $user === null ? $node->getFilesystem()->getUser() : $user;

        if (null === $user) {
            $this->logger->debug('system acl call, grant full access', [
                'category' => get_class($this),
            ]);

            return true;
        }

        if (!isset(self::PRIVILEGES_WEIGHT[$privilege])) {
            throw new Exception('unknown privilege '.$privilege.' requested');
        }

        $priv = $this->getAclPrivilege($node, $user);
        $result = false;

        if (self::PRIVILEGE_WRITEPLUS === $priv && $node->getOwner() == $user->getId()) {
            $result = true;
        } elseif (self::PRIVILEGE_WRITEPLUS !== $priv && self::PRIVILEGES_WEIGHT[$priv] >= self::PRIVILEGES_WEIGHT[$privilege]) {
            $result = true;
        }

        if ($result === true) {
            $this->logger->debug('grant access to node ['.$node->getId().'] for user ['.$user->getId().'] by privilege ['.$priv.']', [
                'category' => get_class($this),
            ]);

            return $result;
        }

        $this->logger->debug('deny access to node ['.$node->getId().'] for user ['.$user->getId().'] by privilege ['.$priv.']', [
            'category' => get_class($this),
        ]);

        return $result;
    }

    /**
     * Get access privilege.
     */
    public function getAclPrivilege(NodeInterface $node, ?User $user = null): string
    {
        $user = $user === null ? $node->getFilesystem()->getUser() : $user;

        if ($node->isShareMember()) {
            return $this->processShareMember($node, $user);
        }
        if ($node->isReference() && $node->isOwnerRequest()) {
            return $this->processShareReference($node, $user);
        }
        if (!$node->isOwnerRequest()) {
            $this->logger->warning('user ['.$user.'] not allowed to access non owned node ['.$node->getId().']', [
                'category' => get_class($this),
            ]);

            return self::PRIVILEGE_DENY;
        }
        if ($node->isOwnerRequest()) {
            return self::PRIVILEGE_MANAGE;
        }

        return self::PRIVILEGE_DENY;
    }

    /**
     * Validate acl.
     */
    public function validateAcl(Server $server, array $acl): bool
    {
        if (0 === count($acl)) {
            throw new Exception('there must be at least one acl rule');
        }

        foreach ($acl as $rule) {
            $this->validateRule($server, $rule);
        }

        return true;
    }

    /**
     * Validate rule.
     */
    public function validateRule(Server $server, array $rule): bool
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

        $this->verifyRole($server, $rule['type'], new ObjectId($rule['id']));

        return true;
    }

    /**
     * Get acl with resolved roles.
     */
    public function resolveAclTable(Server $server, array $acl): array
    {
        foreach ($acl as $key => &$rule) {
            try {
                if ('user' === $rule['type']) {
                    $rule['role'] = $server->getUserById(new ObjectId($rule['id']));
                } elseif ('group' === $rule['type']) {
                    $rule['role'] = $server->getGroupById(new ObjectId($rule['id']));
                } else {
                    throw new Exception('invalid acl rule resource type');
                }
            } catch (\Exception $e) {
                unset($acl[$key]);
                $this->logger->error('acl role ['.$rule['id'].'] could not be resolved, remove from list', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $acl;
    }

    /**
     * Process share member.
     */
    protected function processShareMember(NodeInterface $node, User $user): string
    {
        try {
            $share = $node->getFilesystem()->findRawNode($node->getShareId());
        } catch (\Exception $e) {
            $this->logger->error('could not found share node ['.$node->getShareId().'] for share child node ['.$node->getId().'], dead reference?', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            return self::PRIVILEGE_DENY;
        }

        if ((string) $share['owner'] === (string) $user->getId()) {
            return self::PRIVILEGE_MANAGE;
        }

        $acl = $node->getAttributes()['acl'];
        $share = $this->processRuleset($user, $share['acl']);

        if (count($acl) > 0) {
            $own = $this->processRuleset($user, $node->getAttributes()['acl']);

            if ($share !== self::PRIVILEGE_DENY) {
                return $own;
            }
        }

        return $share;
    }

    /**
     * Process share reference.
     */
    protected function processShareReference(NodeInterface $node, User $user): string
    {
        try {
            $share = $node->getFilesystem()->findRawNode($node->getShareId());
        } catch (\Exception $e) {
            $this->logger->error('could not find share node ['.$node->getShareId().'] for reference ['.$node->getId().'], dead reference?', [
                 'category' => get_class($this),
                 'exception' => $e,
            ]);

            return self::PRIVILEGE_DENY;
        }

        if ($share['deleted'] instanceof UTCDateTime || true !== $share['shared']) {
            $this->logger->error('share node ['.$share['_id'].'] has been deleted, dead reference?', [
                 'category' => get_class($this),
            ]);

            return self::PRIVILEGE_DENY;
        }

        return $this->processRuleset($user, $share['acl']);
    }

    /**
     * Process ruleset.
     */
    protected function processRuleset(User $user, array $acl): string
    {
        $result = self::PRIVILEGE_DENY;
        $groups = $user->getGroups();

        foreach ($acl as $rule) {
            if (self::TYPE_USER === $rule['type'] && $rule['id'] === (string) $user->getId()) {
                $priv = $rule['privilege'];
            } elseif (self::TYPE_GROUP === $rule['type'] && in_array($rule['id'], $groups)) {
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
     * Verify if role exists.
     */
    protected function verifyRole(Server $server, string $type, ObjectId $id): bool
    {
        if (self::TYPE_USER === $type && $server->getUserById($id)) {
            return true;
        }
        if (self::TYPE_GROUP === $type && $server->getGroupById($id)) {
            return true;
        }

        throw new Exception('invalid acl rule resource type');
    }
}
