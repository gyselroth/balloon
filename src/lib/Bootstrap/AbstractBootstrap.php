<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Bootstrap;

use ErrorException;
use Psr\Log\LoggerInterface;

abstract class AbstractBootstrap
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Inject object.
     *
     *
     * @return AbstractBootstrap
     */
    public function inject($object): self
    {
        return $this;
    }

    /**
     * Set error handler.
     *
     * @return AbstractBootstrap
     */
    protected function setErrorHandler(): self
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            $log = $message.' in '.$file.':'.$line;

            switch ($severity) {
                case E_ERROR:
                case E_USER_ERROR:
                    $this->logger->error($log, [
                        'category' => get_class($this),
                    ]);

                break;
                case E_WARNING:
                case E_USER_WARNING:
                    $this->logger->warning($log, [
                        'category' => get_class($this),
                    ]);

                break;
                default:
                    $this->logger->debug($log, [
                        'category' => get_class($this),
                    ]);

                break;
            }
var_dump(error_reporting());
            if (error_reporting() !== 0) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
        });

        return $this;
    }
}
