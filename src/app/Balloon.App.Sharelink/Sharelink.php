<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
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
     */
    public function __construct(Server $server)
    {
        $this->fs = $server->getFilesystem();
    }

    /**
     * Share link.
     */
    public function shareLink(NodeInterface $node, ?string $expiration = null, ?string $password = null): bool
    {
        $set = $node->getAppAttributes(__NAMESPACE__);

        if (!isset($set['token'])) {
            $set['token'] = bin2hex(random_bytes(16));
        }

        if ($expiration !== null) {
            if (empty($expiration) && isset($set['expiration'])) {
                unset($set['expiration']);
            } elseif (!empty($expiration)) {
                $set['expiration'] = (int) $expiration;
            }
        }

        if ($password !== null) {
            if (empty($password) && isset($set['password'])) {
                unset($set['password']);
            } elseif (!empty($password)) {
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
     */
    public function getShareLink(NodeInterface $node): array
    {
        return $node->getAppAttributes(__NAMESPACE__);
    }

    /**
     * Get attributes.
     */
    public function getAttributes(NodeInterface $node, array $attributes = []): array
    {
        return ['shared' => $this->isShareLink($node)];
    }

    /**
     * Check if the node is a shared link.
     */
    public function isShareLink(NodeInterface $node): bool
    {
        return 0 !== count($node->getAppAttributes(__NAMESPACE__));
    }

    /**
     * Get node by access token.
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
