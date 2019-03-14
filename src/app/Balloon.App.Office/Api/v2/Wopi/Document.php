<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Api\v2\Wopi;

use Balloon\App\Office\Session;
use Balloon\App\Office\Session\Member;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Document
{
    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * Get document sesssion information.
     */
    public function get(ObjectId $id, string $access_token): Response
    {
        $session = Member::getByAccessToken($this->server, $this->logger, $id, $access_token);

        return (new Response())->setCode(200)->setBody($session->getAttributes(), true);
    }

    /**
     * Save document contents.
     */
    public function postContents(ObjectId $id, string $access_token): Response
    {
        $session = Session::getByAccessToken($this->server, $id, $access_token);
        $node = $session->getDocument()->getNode();
        ini_set('auto_detect_line_endings', '1');
        $content = fopen('php://input', 'rb');
        $result = $node->put($content, false);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * Get document contents.
     */
    public function getContents(ObjectId $id, string $access_token): void
    {
        $session = Session::getByAccessToken($this->server, $id, $access_token);
        $stream = $session->getDocument()->get();

        while (!feof($stream)) {
            echo fread($stream, 8192);
        }

        exit();
    }
}
