<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Rest\v1;

use \Balloon\Exception;
use \Balloon\Controller;
use \Balloon\App\Office\Document;
use \Balloon\App\Office\Session as WopiSession;
use \Balloon\App\Office\Session\Member;
use \Balloon\Http\Response;
use \MongoDB\BSON\ObjectId;

class Session extends Controller
{
    /**
     * Parse id
     *
     * @param  string $id
     * @return ObjectId
     */
    protected function parseId(string $id): ObjectId
    {
        try {
            return new ObjectId($id);
        } catch (\Exception $e) {
            throw new Exception\InvalidArgument('bad session id given');
        }
    }
    

    /**
     * @api {post} /api/v1/app/office/session Create session
     * @apiName post
     * @apiGroup App\Office
     * @apiPermission none
     * @apiUse _getNode
     * @apiDescription Create new session for a document
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/app/office/session?id=58a18a4ca271f962af6fdbc4"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": {
     *          "id": "544627ed3c58891f058bbbaa"
     *          "access_token": "544627ed3c58891f058b4622",
     *          "access_token_ttl": "1486989000"
     *       }
     * }
     *
     * @param   string $id
     * @param   string $p
     * @return  Response
     */
    public function post(?string $id=null, ?string $p=null): Response
    {
        $node     = $this->fs->getNode($id, $p, 'File');
        $document = new Document($this->fs->getDatabase(), $node);
    
        $session= new WopiSession($this->fs, $document, (int)$this->config->apps->Office->config->token_ttl);
        $member = new Member($this->fs->getUser(), (int)$this->config->apps->Office->config->token_ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(201)->setBody([
            'id'               => (string)$session->getId(),
            'access_token'     => $member->getAccessToken(),
            'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000)
        ]);
    }


    /**
     * @api {post} /api/v1/app/office/session Join session
     * @apiName postJoin
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Create new session for a document
     * @apiParam (GET Parameter) {string} session_id The session id to join to
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/app/office/session/join?session_id=58a18a4ca271f962af6fdbc4"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 201,
     *      "data": {
     *          "access_token": "544627ed3c58891f058b4622",
     *          "access_token_ttl": "1486989000"
     *      }
     * }
     *
     * @param   string $id
     * @return  Response
     */
    public function postJoin(string $id): Response
    {
        $session= WopiSession::getSessionById($this->fs, $this->parseId($id));
        $member = new Member($this->fs->getUser(), (int)$this->config->apps->Office->config->token_ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(200)->setBody([
            'access_token'     => $member->getAccessToken(),
            'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000)
        ]);
    }


    /**
     * @api {delete} /api/v1/app/office/session Delete session
     * @apiName delete
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Delete a running session. If more members are active in the requested session than only the membership gets removed.
     * The session gets completely removed if only one member exists.
     * @apiParam (GET Parameter) {string} session_id The session id to delete
     * @apiParam (GET Parameter) {string} access_token Access token
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v1/app/office/session?session_id=58a18a4ca271f962af6fdbc4&access_token=97223329239823bj223232323"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 OK
     *
     * @param   string $id
     * @param   string $access_token
     * @return  Response
     */
    public function delete(string $id, string $access_token): Response
    {
        $session = WopiSession::getByAccessToken($this->fs, $this->parseId($id), $access_token);
        $session->leave($this->fs->getUser())
                ->store();

        return (new Response())->setCode(204);
    }
}
