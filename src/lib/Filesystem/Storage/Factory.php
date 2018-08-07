<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage;

use Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use Balloon\Filesystem\Storage\Adapter\Smb;
use Psr\Log\LoggerInterface;

class Factory
{
    /**
     * Adapters.
     */
    const ADAPTERS = [
        'smb' => Smb::class,
    ];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Storage handler.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Create adapter.
     */
    public function build(array $options): AdapterInterface
    {
        $options = Validator::validate($options);
        $adapter = self::ADAPTERS[$options['adapter']];

        $validator = $adapter.'\\Validator';
        $factory = $adapter.'\\Factory';

        $validator::validate($options);

        return $factory::build($options, $this->logger);
    }
}
