<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Converter\Adapter;

use \Psr\Log\LoggerInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * LoggerInterface
     *
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * Initialize
     *
     * @param   LoggerInterface $logger
     * @param   Iterable $config
     * @return  void
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config=null)
    {
        $this->logger  = $logger;
        $this->setOptions($config);
    }
}
