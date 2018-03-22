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
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Add a new user';
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
        $options = [];
        if ($this->getopt->getOption('firstname') !== null) {
            $options['first_name'] = $this->getopt->getOption('firstname');
        }

        if ($this->getopt->getOption('lastname') !== null) {
            $options['last_name'] = $this->getopt->getOption('lastname');
        }

        if ($this->getopt->getOption('mail') !== null) {
            $options['mail'] = $this->getopt->getOption('mail');
        }

        if ($this->getopt->getOption('softquota') !== null) {
            $options['soft_quota'] = $this->getopt->getOption('softquota');
        }

        if ($this->getopt->getOption('hardquota') !== null) {
            $options['hard_quota'] = $this->getopt->getOption('hardquota');
        }

        if ($this->getopt->getOption('namespace') !== null) {
            $options['namespace'] = $this->getopt->getOption('namespace');
        }

        if ($this->getopt->getOption('description') !== null) {
            $options['description'] = $this->getopt->getOption('description');
        }

        if ($this->getopt->getOption('password') !== null) {
            $options['password'] = $this->getopt->getOption('password');
        }

        if ($this->getopt->getOption('locale') !== null) {
            $options['locale'] = $this->getopt->getOption('locale');
        }

        if ($this->getopt->getOption('avatar') !== null) {
            $options['avatar'] = new Binary(file_get_contents($this->getopt->getOption('avatar')), Binary::TYPE_GENERIC);
        }

        if ($this->getopt->getOption('admin') !== null) {
            $options['admin'] = true;
        }

        return $options;
    }
}
