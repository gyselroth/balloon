<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Api\v2;

use Balloon\App\Wopi\HostManager;
use Micro\Http\Response;

class Hosts
{
    /**
     * Host manager.
     *
     * @var HostManager
     */
    protected $manager;

    /**
     * Constructor.
     */
    public function __construct(HostManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Get wopi hosts.
     */
    public function get(): Response
    {
        $hosts = $this->manager->getHosts();
        $body = [
            'total' => count($hosts),
            'count' => count($hosts),
            'data' => $hosts,
        ];

        return (new Response())->setCode(200)->setBody($body);
    }
}
