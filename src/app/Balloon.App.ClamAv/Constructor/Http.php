<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv\Constructor;

use Balloon\Filesystem\Node\AttributeDecorator;
use Balloon\Filesystem\Node\File;

class Http
{
    /**
     * Constructor.
     *
     * @param AttributeDecorator $decorator
     */
    public function __construct(AttributeDecorator $decorator)
    {
        $decorator->addDecorator('malware_quarantine', function ($node) {
            if ($node instanceof File) {
                $quarantine = $node->getAppAttribute('Balloon\\App\\ClamAv', 'quarantine');
                if (is_bool($quarantine)) {
                    return $quarantine;
                }
            }
        });
        $decorator->addDecorator('malware_scantime', function ($node) {
            if ($node instanceof File) {
                return $node->getAppAttribute('Balloon\\App\\ClamAv', 'scantime');
            }
        });
        $decorator->addDecorator('malware_reason', function ($node) {
            if ($node instanceof File) {
                return $node->getAppAttribute('Balloon\\App\\ClamAv', 'reason');
            }
        });
    }
}
