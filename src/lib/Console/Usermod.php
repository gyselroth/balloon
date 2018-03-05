<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use GetOpt\GetOpt;

class Usermod extends Useradd
{
    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Modify existing user';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        parent::setOptions();

        $this->getopt->addOptions([
            \GetOpt\Option::create('N', 'new-username', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Specify a new username'),
        ]);

        return $this;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        $options = $this->parseParams();

        if ($this->getopt->getOption('new-username') !== null) {
            $options['username'] = $this->getopt->getOption('new-username');
        }

        $user = $this->server->getUserbyName($this->getopt->getOption('username'));

        $this->logger->info('update user ['.$user->getId().']', [
            'category' => get_class($this),
        ]);

        $user->setAttributes($options);

        return true;
    }
}
