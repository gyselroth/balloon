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
use Balloon\App\Webauthn\Device\DeviceFactory;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Devices
{
    /**
     * DeviceFactory.
     *
     * @var DeviceFactory
     */
    protected $device_factory;

    /**
     * Initialize.
     */
    public function __construct(DeviceFactory $device_factory, CreationChallengeFactory $creation_challenge_factory, Server $server)
    {
        $this->device_factory = $device_factory;
        $this->creation_challenge_factory = $creation_challenge_factory;
        $this->server = $server;
    }

    /**
     * Devices endpoint.
     */
    public function post(string $id, string $type, string $rawId, array $response, ObjectId $challenge): Response
    {
        $challenge = $this->creation_challenge_factory->getOne($challenge);

        $device = $this->device_factory->create($challenge, [
            'id' => $id,
            'type' => $type,
            'rawId' => $rawId,
            'response' => $response,
        ]);

        return (new Response())->setCode(201)->setBody([]);
    }
}
