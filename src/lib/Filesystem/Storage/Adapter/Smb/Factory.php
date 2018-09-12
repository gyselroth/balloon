<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Filesystem\Storage\Adapter\Smb;

use Balloon\Filesystem\Storage\Adapter\AdapterInterface;
use Balloon\Filesystem\Storage\Adapter\Smb;
use Icewind\SMB\AnonymousAuth;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\ServerFactory;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Factory
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Balloon system folder.
     *
     * @var string
     */
    protected $system_folder = '.balloon';

    /**
     * Construct.
     */
    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Create adapter.
     **/
    public function build(array $options): AdapterInterface
    {
        $options = $this->validate($options);

        $factory = new ServerFactory();

        if (!isset($options['username']) || !isset($options['password']) || !isset($options['workgroup'])) {
            $auth = new AnonymousAuth();
        } else {
            $auth = new BasicAuth($options['username'], $options['workgroup'], $options['password']);
        }

        $smb = $factory->createServer($options['host'], $auth);
        $share = $smb->getShare($options['share']);

        return new Smb($share, $this->logger, [
            Smb::OPTION_ROOT => isset($options['root']) ? $options['root'] : '',
            Smb::OPTION_SYSTEM_FOLDER => $this->system_folder,
        ]);
    }

    /**
     * Validate.
     */
    public function validate(array $options): array
    {
        return Validator::validate($options);
    }

    /**
     * Set options.
     */
    protected function setOptions(array $config = []): self
    {
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'system_folder':
                    $this->{$option} = (string) $value;

                break;
                default:
                    throw new InvalidArgumentException('unknown option '.$option.' given');
            }
        }

        return $this;
    }
}
