<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\Bootstrap;

use Balloon\App;
use Balloon\App\Delta;

class Http extends AbstractBootstrap
{
    public static function setupMockServer()
    {
        $server = parent::setupMockServer();
        global $composer;

        $app = new App(App::CONTEXT_HTTP, $composer, $server, self::$logger, self::$app);

        /*            'Balloon.App.Delta' => [],
                    'Balloon.App.Sharelink' => []
                ]);
        */
        return $server;
    }
}
