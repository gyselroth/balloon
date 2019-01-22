<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Notification\Notifier;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator as NodeAttributeDecorator;
use Balloon\Server;
use Micro\Http\Response;

class Subscription extends Controller
{
    /**
     * Notifier.
     *
     * @var Notifier
     */
    protected $notifier;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Role attribute decorator.
     *
     * @var NodeAttributeDecorator
     */
    protected $node_decorator;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Notifier $notifier, NodeAttributeDecorator $node_decorator)
    {
        $this->fs = $server->getFilesystem();
        $this->notifier = $notifier;
        $this->node_decorator = $node_decorator;
    }

    /**
     * @api {post} /api/v2/nodes/:id/subscription Subscribe for node update
     * @apiVersion 2.0.0
     * @apiName postSubscribe
     * @apiGroup App\Notification
     * @apiPermission none
     * @apiDescription Receive node updates
     * @apiUse _getNodes
     * @apiUse _multiError
     *
     * @apiExample (cURL) exmaple:
     * curl -XPOST "https://SERVER/api/v2/notifications/subscribe"
     *
     * @apiSuccessExample {string} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "id": ""
     * }
     *
     * @param null|mixed $id
     * @param null|mixed $p
     */
    public function post($id = null, $p = null, bool $subscribe = true, bool $exclude_me = true, bool $recursive = false)
    {
        $node_decorator = $this->node_decorator;
        $notifier = $this->notifier;

        return $this->bulk($id, $p, function ($node) use ($node_decorator, $notifier, $subscribe, $exclude_me, $recursive) {
            $notifier->subscribeNode($node, $subscribe, $exclude_me, $recursive);

            return [
                'code' => 200,
                'data' => $node_decorator->decorate($node),
            ];
        });
    }
}
