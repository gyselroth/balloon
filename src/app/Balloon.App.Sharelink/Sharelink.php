<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink;

use Balloon\App\AppInterface;
use Balloon\Exception;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use Balloon\Server;
use Micro\Auth;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Http\Response;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Sharelink
{
    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Constructor
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
    public function shareLink(NodeInterface $node, array $options): bool
    {
        $valid = [
            'shared',
            'token',
            'password',
            'expiration',
        ];

        $set = [];
        foreach ($options as $option => $v) {
            if (!in_array($option, $valid, true)) {
                throw new Exception\InvalidArgument('share option '.$option.' is not valid');
            }
            $set[$option] = $v;
        }

        if (!array_key_exists('token', $set)) {
            $set['token'] = bin2hex(random_bytes(16));
        }

        if (array_key_exists('expiration', $set)) {
            if (empty($set['expiration'])) {
                unset($set['expiration']);
            } else {
                $set['expiration'] = (int) $set['expiration'];
            }
        }

        if (array_key_exists('password', $set)) {
            if (empty($set['password'])) {
                unset($set['password']);
            } else {
                $set['password'] = hash('sha256', $set['password']);
            }
        }

        $share = false;
        if (!array_key_exists('shared', $set)) {
            if (0 === count($node->getAppAttributes(__NAMESPACE__))) {
                $share = true;
            }
        } else {
            if ('true' === $set['shared'] || true === $set['shared']) {
                $share = true;
            }

            unset($set['shared']);
        }

        if (true === $share) {
            $node->setAppAttributes(__NAMESPACE__, $set);
        } else {
            $node->unsetAppAttributes(__NAMESPACE__);
        }

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
        $node = $this->fs->findNodeWithCustomFilter([
            'app_attributes.'.$this->getName().'.token' => $token,
            'deleted' => false,
        ]);

        $attributes = $node->getAppAttributes(__NAMESPACE__);

        if ($attributes['token'] !== $token) {
            throw new Exception('token do not match');
        }

        if (isset($attributes['expiration']) && !empty($attributes['expiration'])) {
            $time = (int) $attributes['expiration'];
            if ($time < time()) {
                throw new Exception('share link for this node is expired');
            }
        }

        return $node;
    }
}