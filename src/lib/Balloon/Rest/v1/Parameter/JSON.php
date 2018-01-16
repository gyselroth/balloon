<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Stefan Aebischer <aebischer@pixtron.ch>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\v1\Parameter;

use Balloon\Exception;

class JSON extends \ArrayObject {
    /**
     * Initialize
     *
     * @param  ReflectionParameter $param
     * @param  string $value
     * @return void
     */
    public function __construct(\ReflectionParameter $param, $value)
    {
        if(is_array($value)) {
          $data = $value;
        } else {
          if(!is_string($value)) {
            throw new Exception\InvalidArgument('Parameter '.$param->name.' expects a json string. ' . gettype($value).' given.');
          }

          $data = json_decode($value);

          if($data === null) {
              throw new Exception\InvalidArgument('Parameter '.$param->name.' expects a valid json string. ' . json_last_error_msg());
          }
        }

        return call_user_func_array(array('parent', __FUNCTION__), [$data]);
    }
}
