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
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Factory
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Construct.
     */
    public function __construct(Database $db, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->db = $db;
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

        /*foreach($smb->listShares() as $smb_share) {
            if($smb_share->getName() === $options['share']) {
                $share = $smb_share;
            }
        }

        if($share === null) {
            throw new Exception('share '.$options['share'].' was not found');
        }*/

        return new Smb($share, $this->db, $this->logger);
    }

    /**
     * Validate.
     */
    public function validate(array $options): array
    {
        return Validator::validate($options);
    }
}
