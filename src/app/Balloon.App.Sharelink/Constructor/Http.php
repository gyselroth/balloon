<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink\Constructor;

use Balloon\App\Api\v1\AttributeDecorator\NodeDecorator as NodeAttributeDecoratorv1;
use Balloon\App\Sharelink\Api\v1;
use Balloon\App\Sharelink\Api\v2;
use Balloon\App\Sharelink\Sharelink as Share;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Hook;
use Balloon\Hook\AbstractHook;
use DateTime;
use Micro\Auth\Adapter\None as AuthNone;
use Micro\Auth\Auth;
use Micro\Http\Response;
use Micro\Http\Router;
use Micro\Http\Router\Route;
use Psr\Log\LoggerInterface;

class Http
{
    /**
     * Sharelink.
     *
     * @var Share
     */
    protected $sharelink;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Init.
     */
    public function __construct(Router $router, Hook $hook, Share $sharelink, NodeAttributeDecorator $node_decorator_v2, NodeAttributeDecoratorv1 $node_decorator_v1, LoggerInterface $logger)
    {
        $router
            ->appendRoute(new Route('/share/{t:#(.*+)#}', $this, 'start'))
            ->appendRoute(new Route('/share', $this, 'start'))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/share-link', v1\ShareLink::class))
            ->prependRoute(new Route('/api/v1/(node|file|collection)/{id:#([0-9a-z]{24})#}/share-link', v1\ShareLink::class))
            ->prependRoute(new Route('/api/v2/(nodes|files|collections)/share-link(/|\z)', v2\ShareLink::class))
            ->prependRoute(new Route('/api/v2/(nodes|files|collections)/{id:#([0-9a-z]{24})#}/share-link(/|\z)', v2\ShareLink::class));

        $hook->injectHook(new class() extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if (preg_match('#^/index.php/share#', $_SERVER['ORIG_SCRIPT_NAME'])) {
                    $auth->injectAdapter(new AuthNone());
                }
            }
        });

        $node_decorator_v2->addDecorator('sharelink_has_password', function ($node) use ($sharelink) {
            $attributes = $sharelink->getSharelink($node);

            return isset($attributes['password']);
        });

        $node_decorator_v2->addDecorator('sharelink_token', function ($node) use ($sharelink) {
            $attributes = $sharelink->getSharelink($node);

            if (isset($attributes['token'])) {
                return $attributes['token'];
            }
        });

        $node_decorator_v2->addDecorator('sharelink_expire', function ($node) use ($sharelink) {
            $attributes = $sharelink->getSharelink($node);

            if (isset($attributes['expiration'])) {
                $ts = (new DateTime())->setTimestamp((int) $attributes['expiration']);

                return $ts->format('c');
            }
        });

        $node_decorator_v1->addDecorator('sharelink', function ($node) use ($sharelink) {
            return isset($sharelink->getSharelink($node)['token']);
        });

        $this->logger = $logger;
        $this->sharelink = $sharelink;
    }

    /**
     * Start.
     *
     * @param string $password
     */
    public function start(string $t, bool $download = false, ?string $password = null)
    {
        try {
            $node = $this->sharelink->findNodeWithShareToken($t);
            $share = $node->getAppAttributes('Balloon\\App\\Sharelink');

            if (array_key_exists('password', $share)) {
                $valid = false;
                if ($password !== null) {
                    $valid = hash('sha256', $password) === $share['password'];
                }

                if (false === $valid) {
                    echo "<form method=\"post\">\n";
                    echo    "Password: <input type=\"password\" name=\"password\"/>\n";
                    echo    "<input type=\"submit\" value=\"Submit\"/>\n";
                    echo "</form>\n";
                    exit();
                }
            }

            if ($node instanceof Collection) {
                $mime = 'application/zip';
                $stream = $node->getZip();
                $name = $node->getName().'.zip';
            } else {
                $mime = $node->getContentType();
                $stream = $node->get();
                $name = $node->getName();
            }

            if (true === $download || preg_match('#html#', $mime)) {
                header('Content-Disposition: attachment; filename*=UTF-8\'\''.rawurlencode($name));
                header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                header('Content-Type: application/octet-stream');
                header('Content-Length: '.$node->getSize());
                header('Content-Transfer-Encoding: binary');
            } else {
                header('Content-Disposition: inline; filename="'.$name.'"');
                header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                header('Content-Type: '.$mime);
            }

            if (null === $stream) {
                exit();
            }

            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
        } catch (\Exception $e) {
            $this->logger->error('failed load node with given access token', [
                'category' => get_class($this),
                'exception' => $e,
            ]);

            return (new Response())
                ->setOutputFormat('text')
                ->setCode(404)
                ->setBody('Token is invalid or share link is expired');
        }
    }
}
