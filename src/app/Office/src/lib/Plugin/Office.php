<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Plugin;

use Balloon\Exception;
use Balloon\Logger;
use Balloon\Config;
use Balloon\Plugin\AbstractPlugin;
use Balloon\Auth\Adapter\None as AuthNone;

class Office extends AbstractPlugin
{
    /**
     * Execute plugin
     *
     * @param   array $adapter
     * @return  void
     */
    public function preAuthentication(array &$adapter): void
    {
        $skip = [
            '/index.php/api/v1/app/office/wopi/document',
            '/index.php/api/v1/app/office/wopi/document/contents'
        ];
        
        foreach ($skip as $path) {
            if (preg_match('#^'.$path.'#', $_SERVER["ORIG_SCRIPT_NAME"])) {
                $adapter = [(new AuthNone((new Config()), $this->logger))];
                break;
            }
        }
    }
}
