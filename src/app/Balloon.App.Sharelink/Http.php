<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink;

use \Balloon\Exception;
use \Balloon\App;
use \Balloon\Filesystem;
use \Balloon\Filesystem\Node\NodeInterface;
use \Balloon\Filesystem\Node\Collection;
use \Micro\Http\Response;
use \Micro\Http\Router\Route;
use \Balloon\App\AbstractApp;
use \Balloon\Hook\AbstractHook;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;
use \Balloon\App\Sharelink\Api\v1\ShareLink;

class Http extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->router
            ->appendRoute(new Route('/share', $this, 'start'))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/share-link', ShareLink::class))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/{id:#([0-9a-z]{24})#}/share-link', ShareLink::class));

        $this->server->getHook()->injectHook(new class($this->logger) extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if (preg_match('#^/index.php/share#', $_SERVER["ORIG_SCRIPT_NAME"])) {
                    $auth->injectAdapter('none', (new AuthNone($this->logger)));
                }
            }
        });
 
        return true;
    }


    /**
     * Share link
     *
     * @param   NodeInterface $node
     * @param   array $options
     * @return  bool
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
            } else {
                $set[$option] = $v;
            }
        }

        if (!array_key_exists('token', $set)) {
            $set['token'] = bin2hex(random_bytes(16));
        }

        if (array_key_exists('expiration', $set)) {
            if (empty($set['expiration'])) {
                unset($set['expiration']);
            } else {
                $set['expiration'] = (int)$set['expiration'];
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
            if (count($node->getAppAttributes($this)) === 0) {
                $share = true;
            }
        } else {
            if ($set['shared'] === 'true' || $set['shared'] === true) {
                $share = true;
            }

            unset($set['shared']);
        }
        
        if ($share === true) {
            $node->setAppAttributes($this, $set);
        } else {
            $node->unsetAppAttributes($this);
        }
    
        return true;
    }


    /**
     * Get share options
     *
     * @param  NodeInterface $node
     * @return array
     */
    public function getShareLink(NodeInterface $node): array
    {
        return $node->getAppAttributes($this);
    }
    

    /**
     * Get attributes
     *
     * @param  NodeInterface $node
     * @param  array $attributes
     * @return array
     */
    public function getAttributes(NodeInterface $node, array $attributes=[]): array
    {
        return ['shared' => $this->isShareLink($node)];
    }


    /**
     * Check if the node is a shared link
     *
     * @param  NodeInterface $node
     * @return bool
     */
    public function isShareLink(NodeInterface $node): bool
    {
        return count($node->getAppAttributes($this)) !== 0;
    }


    /**
     * Get node by access token
     *
     * @param   string $token
     * @return  NodeInterface
     */
    public function findNodeWithShareToken(string $token): NodeInterface
    {
        $node = $this->fs->findNodeWithCustomFilter([
            'app_attributes.'.$this->getName().'.token' => $token,
            'deleted' => false,
        ]);

        $attributes = $node->getAppAttributes($this);

        if ($attributes['token'] !== $token) {
            throw new Exception('token do not match');
        }
        
        if (isset($attributes['expiration']) && !empty($attributes['expiration'])) {
            $time = (int)$attributes['expiration'];
            if ($time < time()) {
                throw new Exception('share link for this node is expired');
            }
        }

        return $node;
    }

        
    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        if (isset($_GET['t']) && !empty($_GET['t'])) {
            $token    = $_GET['t'];
            if (isset($_GET['download'])) {
                $download = (bool)$_GET['download'];
            } else {
                $download = false;
            }

            try {
                $node  = $this->findNodeWithShareToken($token);
                $share = $node->getAppAttributes($this);
                
                if (array_key_exists('password', $share)) {
                    $valid = false;
                    if (isset($_POST['password'])) {
                        $valid = hash('sha256', $_POST['password']) === $share['password'];
                    }

                    if ($valid === false) {
                        echo "<form method=\"post\">\n";
                        echo    "Password: <input type=\"password\" name=\"password\"/>\n";
                        echo    "<input type=\"submit\" value=\"Submit\"/>\n";
                        echo "</form>\n";
                        exit();
                    }
                }

                if ($node instanceof Collection) {
                    $mime   = 'application/zip';
                    $stream = $node->getZip();
                    $name   = $node->getName().'.zip';
                } else {
                    $mime   = $node->getMime();
                    $stream = $node->get();
                    $name   = $node->getName();
                }

                if ($download === true || preg_match('#html#', $mime)) {
                    header('Content-Disposition: attachment; filename*=UTF-8\'\'' .rawurlencode($name));
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: application/octet-stream');
                    header('Content-Length: '.$node->getSize());
                    header('Content-Transfer-Encoding: binary');
                } else {
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: '.$mime);
                }

                if ($stream === null) {
                    exit();
                }

                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
            } catch (\Exception $e) {
                $this->logger->error("failed load node with access token [$token]", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                (new Response())
                    ->setOutputFormat('text')
                    ->setCode(404)
                    ->setBody('Token is invalid or share link is expired')
                    ->send();
            }
        } else {
            (new Response())
                ->setOutputFormat('text')
                ->setCode(401)
                ->setBody('No token submited')
                ->send();
        }

        return true;
    }
}
