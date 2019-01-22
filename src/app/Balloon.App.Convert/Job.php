<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Server;
use TaskScheduler\AbstractJob;
use TaskScheduler\Scheduler;

class Job extends AbstractJob
{
    /**
     * Converter.
     *
     * @var Converter
     */
    protected $converter;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Scheduler.
     *
     * @var Scheduler
     */
    protected $scheduler;

    /**
     * Constructor.
     */
    public function __construct(Server $server, Scheduler $scheduler, Converter $converter)
    {
        $this->server = $server;
        $this->scheduler = $scheduler;
        $this->converter = $converter;
    }

    /**
     * Start job.
     */
    public function start(): bool
    {
        $master = $this->server->getFilesystem()->findNodeById($this->data['master']);

        if (isset($this->data['id'])) {
            $this->converter->convert($this->data['id'], $master);
        } else {
            foreach ($this->converter->getSlaves($master) as $slave) {
                $this->scheduler->addJob(self::class, [
                    'master' => $this->data['master'],
                    'id' => $slave['_id'],
                ]);
            }
        }

        return true;
    }
}
