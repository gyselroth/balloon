<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Cli\Console;

use Balloon\Server;
use GetOpt\GetOpt;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectId;
use Psr\Log\LoggerInterface;

class User
{
    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     * @param GetOpt          $getopt
     */
    public function __construct(Server $server, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->server = $server;
        $this->logger = $logger;
        $this->getopt = $getopt;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function add(): bool
    {
        $options = $this->parseParams();
        $result = $this->server->addUser($this->getopt->getOption('username'), $options);

        $this->logger->info('new user ['.$result.'] created', [
            'category' => get_class($this),
        ]);

        return true;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function edit(): bool
    {
        $id = new ObjectId($this->getopt->getOperand('id'));
        $options = $this->parseParams();

        $user = $this->server->getUserById($id);

        $this->logger->info('update user ['.$user->getId().']', [
            'category' => get_class($this),
        ]);

        if ($this->getopt->getOption('username') !== null) {
            $options['username'] = $this->getopt->getOption('username');
        }

        $user->setAttributes($options);

        return true;
    }

    /**
     * Parse params.
     *
     * @return arrray
     */
    protected function parseParams(): array
    {
        $map = [
            'mail' => 'mail',
            'softquota' => 'soft_quota',
            'hardquota' => 'hard_quota',
            'namespace' => 'namespace',
            'password' => 'password',
            'locale' => 'locale',
            'avatar' => 'avatar',
            'admin' => 'admin',
        ];

        $options = array_intersect_key($this->getopt->getOptions(), $map);
        ksort($options);
        $map = array_intersect_key($map, $options);
        ksort($map);
        $options = array_combine(array_values($map), $options);

        if (isset($options['avatar'])) {
            $options['avatar'] = new Binary(file_get_contents($options['avatar']), Binary::TYPE_GENERIC);
        }

        return $options;
    }
}
