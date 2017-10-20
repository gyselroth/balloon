<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Testsuite\Unit\App\Sharelink;

use Balloon\Testsuite\Unit\DatabaseTest as CoreDatabaseTest;

/**
 * @coversNothing
 */
class DatabaseTest extends CoreDatabaseTest
{
    protected $server;

    public function setUp()
    {
        $server = self::setupMockServer();
        $server->getApp()->registerApp('Balloon.App.Sharelink');
        $this->expected_indices[] = 'balloon.storage.app_attributes.Balloon_App_Sharelink.token_1';

        $this->server = $server;
    }
}
