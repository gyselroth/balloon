<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Convert;

use \Psr\Log\LoggerInterface as Logger;
use \Balloon\Server;
use \Balloon\Async\AbstractJob;

class Job extends AbstractJob
{
    /**
     * Start job
     *
     * @param  Server $server
     * @param  Logger $logger
     * @return bool
     */
    public function start(Server $server, Logger $logger): bool
    {
        $file = $server->getFilesystem()->findNodeWithId($this->data['id']);

        $logger->info("create shadow for node [".$this->data['id']."]", [
            'category' => get_class($this),
        ]);
        
        $app = $server->getApp()
            ->getApp('Balloon.App.Convert');

        $result = $app->getConverter()
            ->convert($file, $this->data['format']);

        $file->setFilesystem($server->getUserById($file->getOwner())->getFilesystem());

        try {
            $shadows = $file->getAppAttribute($app, 'shadows');
            if (is_array($shadows) && isset($shadows[$this->data['format']])) {
                $shadow = $file->getFilesystem()->findNodeWithId($shadows[$format]);
                $shadow->put($result->getPath());
                return true;
            }
        } catch (\Exception $e) {
            $logger->debug('referenced shadow node ['.$shadows[$this->data['format']].'] does not exists or is not accessible', [
                'category' => get_class($this),
                'exception'=> $e
            ]);
        }

        $logger->debug('create non existing shadow node for ['.$this->data['id'].']', [
            'category' => get_class($this)
        ]);
       
        try {
            $name = substr($file->getName(), -strlen($file->getExtension()));
            $name .= $this->data['format'];
        } catch (\Exception $e) {
            $name = $file->getName().'.'.$this->data['format'];
        }
        
        $shadow = $file->getParent()->createFile($name, $result->getPath(), [
            'owner' => $file->getOwner(),
            'app'   => [
                $app->getName() => [
                    'master' => $file->getId()
                ]
            ]
        ]);

        $file->setAppAttribute($app, 'shadows.'.$this->data['format'], $shadow->getId());

        return true;
    }
}
