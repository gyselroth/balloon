<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use Balloon\Async\AbstractJob;
use Balloon\Converter;
use Balloon\Server;
use Psr\Log\LoggerInterface;

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
        $file = $this->server->getFilesystem()->findNodeWithId($this->data['id']);

        $this->logger->info('create shadow for node ['.$this->data['id'].']', [
            'category' => get_class($this),
        ]);

        $result = $htis->converter->convert($file, $this->data['format']);

        $file->setFilesystem($this->server->getUserById($file->getOwner())->getFilesystem());

        try {
            $shadows = $file->getAppAttribute(__NAMESPACE__, 'shadows');
            if (is_array($shadows) && isset($shadows[$this->data['format']])) {
                $shadow = $file->getFilesystem()->findNodeWithId($shadows[$format]);
                $shadow->put($result->getPath());

                return true;
            }
        } catch (\Exception $e) {
            $this->logger->debug('referenced shadow node ['.$shadows[$this->data['format']].'] does not exists or is not accessible', [
                'category' => get_class($this),
                'exception' => $e,
            ]);
        }

        $this->logger->debug('create non existing shadow node for ['.$this->data['id'].']', [
            'category' => get_class($this),
        ]);

        try {
            $name = substr($file->getName(), -strlen($file->getExtension()));
            $name .= $this->data['format'];
        } catch (\Exception $e) {
            $name = $file->getName().'.'.$this->data['format'];
        }

        $shadow = $file->getParent()->createFile($name, $result->getPath(), [
            'owner' => $file->getOwner(),
            'app' => [
                $app->getName() => [
                    'master' => $file->getId(),
                ],
            ],
        ]);

        $file->setAppAttribute(__NAMESPACE__, 'shadows.'.$this->data['format'], $shadow->getId());

        return true;
    }
}
