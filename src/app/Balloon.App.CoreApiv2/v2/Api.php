<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\CoreApiv2\v2;

use Micro\Http\Response;

class Api
{
    /**
     * Server & API Status.
     */
    public function get(): Response
    {
        $data = [
            'name' => 'balloon',
            'api_version' => 2,
        ];

        return (new Response())->setCode(200)->setBody($data);
    }
}
