<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Webauthn\Api\v2;

use Balloon\App\Webauthn\CreationChallenge\CreationChallengeFactory;
use Balloon\Server;
use Micro\Http\Response;

class CreationChallenges
{
    /**
     * ChallengeFactory.
     *
     * @var ChallengeFactory
     */
    protected $creation_challenge_factory;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Initialize.
     */
    public function __construct(CreationChallengeFactory $creation_challenge_factory, Server $server)
    {
        $this->creation_challenge_factory = $creation_challenge_factory;
        $this->server = $server;
    }

    /**
     * Challenge endpoint.
     */
    public function post(string $domain): Response
    {
        $resource = $this->creation_challenge_factory->create($this->server->getIdentity(), $domain);

        return (new Response())->setCode(201)->setBody([
            'id' => (string) $resource['id'],
            'key' => $resource['key'],
        ]);
    }
}
