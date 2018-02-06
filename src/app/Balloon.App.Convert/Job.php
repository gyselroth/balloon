<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Converter;
use Balloon\Exception\NotFound;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server;
use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;

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
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param Async $async
     */
    public function __construct(Converter $converter, Server $server, LoggerInterface $logger)
    {
        $this->converter = $converter;
        $this->server = $server;
        $this->logger = $logger;
    }

    /**
     * Start job.
     *
     * @param Server          $server
     * @param LoggerInterface $logger
     *
     * @return bool
     */
    public function start(): bool
    {
        $file = $this->server->getFilesystem()->findNodeById($this->data['node']);

        $this->logger->info('create slave for node ['.$this->data['node'].']', [
            'category' => get_class($this),
        ]);

        $slaves = $file->getAppAttribute(__NAMESPACE__, 'slaves');

        if (is_array($slaves) && isset($slaves[(string) $this->data['slave']])) {
            $slave = $slaves[(string) $this->data['slave']];
        } else {
            throw new Exception('unknown slave node');
        }

        $result = $this->converter->convert($file, $slave['format']);
        $file->setFilesystem($this->server->getUserById($file->getOwner())->getFilesystem());

        try {
            $slaves = $file->getAppAttribute(__NAMESPACE__, 'slaves');
            if (is_array($slaves) && isset($slave['node'])) {
                $slave = $file->getFilesystem()->findNodeById($slave['node']);

                $slave->setReadonly(false);
                $slave->put($result->getPath());
                $slave->setReadonly();

                return true;
            }
        } catch (NotFound $e) {
            $this->logger->debug('referenced slave node ['.$slave['format'].'] does not exists or is not accessible', [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        }

        $this->logger->debug('create non existing slave ['.$this->data['slave'].'] node for ['.$this->data['node'].']', [
            'category' => get_class($this),
        ]);

        try {
            $name = substr($file->getName(), 0, (strlen($file->getExtension()) + 1) * -1);
            $name .= '.'.$slave['format'];
        } catch (\Exception $e) {
            $name = $file->getName().'.'.$slave['format'];
        }

        $node = $file->getParent()->addFile($name, $result->getPath(), [
            'owner' => $file->getOwner(),
            'app' => [
                __NAMESPACE__ => [
                    'master' => $file->getId(),
                ],
            ],
        ], NodeInterface::CONFLICT_RENAME);

        $node->setReadonly();

        $slaves[(string) $this->data['slave']]['node'] = $node->getId();
        $file->setAppAttribute(__NAMESPACE__, 'slaves', $slaves);

        return true;
    }
}
