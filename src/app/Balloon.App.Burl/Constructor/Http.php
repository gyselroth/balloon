<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Burl\Constructor;

use MimeType\Mapping;

class Http
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        Mapping::$types['burl'] = 'application/vnd.balloon.burl';

        return true;
    }
}
