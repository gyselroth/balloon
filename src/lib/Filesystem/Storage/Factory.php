<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage;

use Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use Balloon\Filesystem\Storage\Adapter\Smb;
use Balloon\Filesystem\Storage\Adapter\Smb\Factory as SmbFactory;
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
     * SMB factory.
     *
     * @var SmbFactory
     */
    protected $smb_factory;

    /**
     * Storage handler.
     */
    public function __construct(LoggerInterface $logger, SmbFactory $smb_factory)
    {
        $this->logger = $logger;
        $this->smb_factory = $smb_factory;
    }

    /**
     * Create adapter.
     */
    public function build(array $options): AdapterInterface
    {
        $options = Validator::validate($options);
        $adapter = self::ADAPTERS[$options['adapter']];

        return $this->smb_factory->build($options);
    }
}
