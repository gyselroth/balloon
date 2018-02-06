<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Api\v1\Wopi;

use Balloon\App\Api\Controller;
use Balloon\App\Office\Session;
use Balloon\App\Office\Session\Member;
use Balloon\Exception;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class Document extends Controller
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
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     */
    public function __construct(Server $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * @api {get} /api/v1/office/wopi/document Get document sesssion information
     * @apiName get
     * @apiVersion 1.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Get document session information including document owner, session user and document size
     *
     * @apiParam (GET Parameter) {string} id The document id
     * @apiParam (GET Parameter) {string} access_token An access token to access the document
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/office/wopi/document/58a18a4ca271f962af6fdbc4?access_token=aae366363ee743412abb"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      [***]
     * }
     *
     * @param string $id
     * @param string $access_token
     *
     * @return Response
     */
    public function get(string $id, string $access_token): Response
    {
        $session = Member::getByAccessToken($this->server, $this->logger, $this->parseId($id), $access_token);

        return (new Response())->setCode(200)->setBody($session->getAttributes(), true);
    }

    /**
     * @api {post} /api/v1/office/wopi/document/contents Save document contents
     * @apiName postContents
     * @apiVersion 1.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Save document contents
     *
     * @apiParam (GET Parameter) {string} id The document id
     * @apiParam (GET Parameter) {string} access_token An access token to access the document
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/office/wopi/document/58a18a4ca271f962af6fdbaa/contents?access_token=aae366363ee743412abb"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": true
     * }
     *
     * @param string $id
     * @param string $access_token
     *
     * @return Response
     */
    public function postContents(string $id, string $access_token): Response
    {
        $session = Session::getByAccessToken($this->server, $this->parseId($id), $access_token);
        $node = $session->getDocument()->getNode();
        ini_set('auto_detect_line_endings', '1');
        $content = fopen('php://input', 'rb');
        $result = $node->put($content, false);

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {get} /api/v1/office/wopi/document/contents Get document contents
     * @apiName getContents
     * @apiVersion 1.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Get document contents
     *
     * @apiParam (GET Parameter) {string} id The document id
     * @apiParam (GET Parameter) {string} access_token An access token to access the document
     *
     * @apiExample (cURL) Exampl:
     * curl -XGET "https://SERVER/api/v1/office/document/58a18a4ca271f962af6fdbaa/contents?access_token=aae366363ee743412abb"
     *
     * @apiSuccessExample {binary} Success-Response:
     * HTTP/1.1 200 OK
     *
     * @param string $id
     * @param string $access_token
     */
    public function getContents(string $id, string $access_token): void
    {
        $session = Session::getByAccessToken($this->server, $this->parseId($id), $access_token);
        $stream = $session->getDocument()->get();

        while (!feof($stream)) {
            echo fread($stream, 8192);
        }

        exit();
    }

    /**
     * Get by access token.
     *
     * @param string $id
     * @param string $access_token
     *
     * @return Session
     */
    protected function parseId(string $id): ObjectId
    {
        try {
            return new ObjectId($id);
        } catch (\Exception $e) {
            throw new Exception\InvalidArgument('bad session id given');
        }
    }
}
