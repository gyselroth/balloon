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

use Balloon\App\AppInterface;
use Balloon\Filesystem\Acl\Exception;
use Balloon\Filesystem;
use Balloon\Helper;
use Balloon\Server\User;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Normalizer;
use PHPZip\Zip\Stream\ZipStream;
use Sabre\DAV;
use MongoDB\Database;
use Balloon\Filesystem\Storage;
use Psr\Log\LoggerInterface;
use Balloon\Hook;
use Balloon\Filesystem\Node\NodeInterface;

class Acl
{
    /**
     * ACL privileges weight table
     */
    const PRIVILEGES_WEIGHT = [
        'd'  => 0,
        'r'  => 1,
        'w'  => 2,
        'w+' => 3,
        'rw' => 4,
        'm'  => 5,
    ];


    /**
     * Types
     */
    const TYPE_USER = 'user';
    const TYPE_GROUP = 'group';


    /**
     * Logger
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Check acl.
     *
     * @param NodeInterface $node
     * @param string $privilege
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

        if(!isset(self::PRIVILEGES_WEIGHT[$privilege])) {
            throw new Exception('unknown privilege '.$privilege.' requested');
        }

        $priv = $this->getAclPrivilege($node);

        $result = false;

        if('w+' === $priv && $node->isOwnerRequest()) {
            $result = true;
        } elseif('w+' !== $priv && self::PRIVILEGES_WEIGHT[$priv] >= self::PRIVILEGES_WEIGHT[$privilege]) {
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
            if($rule['type'] === self::TYPE_USER && $rule['id'] === (string)$user->getId()) {
                $priv = $rule['privilege'];
            } elseif($rule['type'] === self::TYPE_GROUP && in_array($rule['id'], $groups)) {
                $priv = $rule['privilege'];
            } else {
                continue;
            }

            if(self::PRIVILEGES_WEIGHT[$priv] > self::PRIVILEGES_WEIGHT[$result]) {
                $result = $priv;
            }
        }

        return $result;
    }


    /**
     * Validate acl
     *
     * @param array $acl
     * @return bool
     */
    public function validateAcl(array $acl): bool
    {
        if(count($acl) === 0) {
            throw new Exception('there must be at least one acl rule');
        }

        foreach($acl as $rule) {
            $this->validateRule($rule);
        }

        return true;
    }


    /**
     * Validate rule
     *
     * @param array $rule
     * @return bool
     */
    public function validateRule(array $rule): bool
    {
        if(!isset($rule['type']) || $rule['type'] !== self::TYPE_USER && $rule['type'] !== self::TYPE_GROUP) {
            throw new Exception('rule must contain either a type group or user');
        }

        if(!isset($rule['type']) || !isset(self::PRIVILEGES_WEIGHT[$rule['privilege']])) {
            throw new Exception('rule must contain a valid privilege');
        }

        if(!isset($rule['id'])) {
            throw new Exception('rule must contain a resource id');
        }

        return true;
    }
}
