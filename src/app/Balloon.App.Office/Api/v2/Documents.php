<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Api\v2;

use Balloon\App\Api\Controller;
use Balloon\App\Office\Constructor\Http as App;
use Balloon\App\Office\Document as OfficeDoc;
use Balloon\App\Office\Template;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Documents extends Controller
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Decorator.
     *
     * @var AttributeDecorator
     */
    protected $decorator;

    /**
     * Constructor.
     */
    public function __construct(App $app, Server $server, AttributeDecorator $decorator)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->app = $app;
        $this->decorator = $decorator;
    }

    /**
     * @api {get} /api/v2/office/documents/:id Get document
     * @apiName get
     * @apiVersion 2.0.0
     * @apiUse _getNode
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Retreive office document
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v2/office/documents/544627ed3c58891f058b4611"
     * curl -XGET "https://SERVER/api/v2/office/documents?id=544627ed3c58891f058b4611"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "loleaflet": "https:\/\/officeserver:9980\/loleaflet\/dist\/loleaflet.html",
     *      "sessions": []
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function get(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p, File::class);
        $document = new OfficeDoc($this->fs->getDatabase(), $node);
        $sessions = [];

        foreach ($document->getSessions() as $session) {
            $sessions[] = [
                'id' => (string) $session['_id'],
                'created' => $session['_id']->getTimestamp(),
                'user' => [
                    'id' => (string) $session['user'],
                    'name' => $this->server->getUserById($session['user'])->getUsername(),
                ],
            ];
        }

        $result = [
            'loleaflet' => $this->app->getLoleaflet(),
            'session' => $sessions,
        ];

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {put} /api/v2/office/documents Create new empty document
     * @apiName post
     * @apiVersion 2.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apuUse _conflictNode
     * @apiDescription Create new document from an existing office document template, option type has to be one of the follwing:
     *  - xlsx  => "Office Open XML Spreadsheet",
     *  - xls   => "Microsoft Excel 97-2003",
     *  - xlt   => "Microsoft Excel 97-2003 Template",
     *  - csv   => "Text CSV",
     *  - ods   => "ODF Spreadsheet",
     *  - ots   => "ODF Spreadsheet Template",
     *  - docx  => "Office Open XML Text",
     *  - doc   => "Microsoft Word 97-2003",
     *  - dot   => "Microsoft Word 97-2003 Template",
     *  - odt   => "ODF Textdocument",
     *  - ott   => "ODF Textdocument Template",
     *  - pptx  => "Office Open XML Presentation",
     *  - ppt   => "Microsoft Powerpoint 97-2003",
     *  - potm  => "Microsoft Powerpoint 97-2003 Template",
     *  - odp   => "ODF Presentation",
     *  - otp   => "ODF Presentation Template"
     *
     * @apiParam (GET Parameter) {string} name The name of the new document
     * @apiParam (GET Parameter) {string} [collection] Parent collection id (If none  given, the document will be placed under root)
     * @apiParam (GET Parameter) {string} type Office document file type
     * @apiParam (GET Parameter) {bool} [readonly] Mark node readonly
     * @apiParam (GET Parameter) {object} [meta] Meta attributes
     *
     * @apiExample (cURL) example:
     * curl -XPUT "https://SERVER/api/v2/office/documents?type=xlsx"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "id": "544627ed3c58891f058b4611"
     * }
     *
     * @param string $collection
     * @param bool   $readonly
     * @param array  $meta
     */
    public function post(string $name, string $type, ?string $collection = null, int $conflict = 0, ?bool $readonly = null, ?array $meta = null): Response
    {
        $parent = $this->fs->getNode($collection, null, Collection::class, false, true);
        $tpl = new Template($type);

        $attributes = compact('readonly', 'meta');
        $attributes = array_filter($attributes, function ($attribute) {return !is_null($attribute); });

        $stream = $tpl->get();
        $storage = $parent->getStorage();
        $session = $storage->storeTemporaryFile($stream, $this->server->getIdentity());
        $result = $parent->addFile($name, $session, $attributes);
        fclose($stream);
        $result = $this->decorator->decorate($result);

        return (new Response())->setCode(201)->setBody($result);
    }
}
