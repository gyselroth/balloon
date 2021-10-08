<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\Api\v2;

use Balloon\App\Webauthn\RequestChallenge\RequestChallengeFactory;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class RequestChallenges
{
    /**
     * RequestChallengeFactory.
     *
     * @var RequestChallengeFactory
     */
    protected $request_challenge_factory;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Initialize.
     */
    public function __construct(RequestChallengeFactory $request_challenge_factory, Server $server)
    {
        $this->request_challenge_factory = $request_challenge_factory;
        $this->server = $server;
    }

    /**
     * Challenge endpoint.
     */
    public function post(ObjectId $id, string $domain): Response
    {
        $resource = $this->request_challenge_factory->create($id, $domain);

        return (new Response())->setCode(201)->setBody([
            'id' => (string) $resource['id'],
            'key' => $resource['key'],
        ]);
    }
}
