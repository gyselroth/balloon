<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink;

use Balloon\Filesystem;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;

class Sharelink
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor.
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->fs = $server->getFilesystem();
    }

    /**
     * Share link.
     *
     * @param NodeInterface $node
     * @param array         $options
     *
     * @return bool
     */
    public function shareLink(NodeInterface $node, ?string $expiration = null, ?string $password = null): bool
    {
        $set = $node->getAppAttributes(__NAMESPACE__);

        if (!isset($set['token'])) {
            $set['token'] = bin2hex(random_bytes(16));
        }

        if ($expiration !== null) {
            if (empty($set['expiration'])) {
                unset($set['expiration']);
            } else {
                $set['expiration'] = (int) $expiration;
            }
        }

        if ($password !== null) {
            if (empty($set['password'])) {
                unset($set['password']);
            } else {
                $set['password'] = hash('sha256', $password);
            }
        }

        $node->setAppAttributes(__NAMESPACE__, $set);

        return true;
    }

    /**
     * Delete sharelink.
     */
    public function deleteShareLink(NodeInterface $node): bool
    {
        $node->unsetAppAttributes(__NAMESPACE__);

        return true;
    }

    /**
     * Get share options.
     *
     * @param NodeInterface $node
     *
     * @return array
     */
    public function getShareLink(NodeInterface $node): array
    {
        return $node->getAppAttributes(__NAMESPACE__);
    }

    /**
     * Get attributes.
     *
     * @param NodeInterface $node
     * @param array         $attributes
     *
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes = []): array
    {
        return ['shared' => $this->isShareLink($node)];
    }

    /**
     * Check if the node is a shared link.
     *
     * @param NodeInterface $node
     *
     * @return bool
     */
    public function isShareLink(NodeInterface $node): bool
    {
        return 0 !== count($node->getAppAttributes(__NAMESPACE__));
    }

    /**
     * Get node by access token.
     *
     * @param string $token
     *
     * @return NodeInterface
     */
    public function findNodeWithShareToken(string $token): NodeInterface
    {
        $node = $this->fs->findNodeByFilter([
            'app.'.__NAMESPACE__.'.token' => $token,
            'deleted' => false,
        ]);

        $attributes = $node->getAppAttributes(__NAMESPACE__);

        if ($attributes['token'] !== $token) {
            throw new Exception\TokenInvalid('token given is invalid');
        }

        if (isset($attributes['expiration']) && !empty($attributes['expiration'])) {
            $time = (int) $attributes['expiration'];
            if ($time < time()) {
                throw new Exception\LinkExpired('link is expired');
            }
        }

        return $node;
    }
}
