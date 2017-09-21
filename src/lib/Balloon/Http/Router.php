<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Http;

use \Balloon\Exception;
use \Balloon\Helper;
use \Micro\Http\Router\Route;
use \ReflectionMethod;
use \Micro\Http\Router as MicroRouter;
use \Micro\Http\Response;

class Router extends MicroRouter
{
    /**
     * Sends a exception response to the client
     *
     * @param   \Exception $exception
     * @return  void
     */
    public function sendException(\Exception $exception): void
    {
        $message = $exception->getMessage();
        $msg = [
            'error'   => get_class($exception),
            'message' => $message,
            'code'    => $exception->getCode()
        ];

        switch (get_class($exception)) {
           case 'Balloon\\Exception\\InvalidArgument':
           case 'Micro\\Http\\Exception':
               $code = 400;
           break;
           case 'Balloon\\Exception\\NotFound':
               $code = 404;
           break;
           case 'Balloon\\Exception\\Forbidden':
               $code = 403;
           break;
           case 'Balloon\\Exception\\InsufficientStorage':
               $code = 507;
           break;
           default:
              $code = 500;
           break;
        }

        $this->logger->error('uncaught exception '.$message.']', [
            'category' => get_class($this),
            'exception' => $exception,
        ]);

        $this->sendResponse($code, $msg);
    }

    // TODO: move this to MicroRouter
    protected function sendResponse(int $code, $body): void
    {
        (new Response())
            ->setCode($code)
            ->setBody($body)
            ->send();
    }
}
