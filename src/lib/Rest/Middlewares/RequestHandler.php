<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest\Middlewares;

use InvalidArgumentException;
use Middlewares\Utils\RequestHandlerContainer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;

class RequestHandler implements MiddlewareInterface
{
    /**
     * @var ContainerInterface Used to resolve the handlers
     */
    private $container;

    /**
     * @var string Attribute name for handler reference
     */
    private $handlerAttribute = 'request-handler';

    /**
     * Set the resolver instance.
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->container = $container ?: new RequestHandlerContainer();
    }

    /**
     * Set the attribute name to store handler reference.
     */
    public function handlerAttribute(string $handlerAttribute): self
    {
        $this->handlerAttribute = $handlerAttribute;

        return $this;
    }

    /**
     * Process a server request and return a response.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $requestHandler = $request->getAttribute($this->handlerAttribute);

        if (is_string($requestHandler)) {
            $requestHandler = $this->container->get($requestHandler);
        }
        if ($requestHandler instanceof MiddlewareInterface) {
            return $requestHandler->process($request, $handler);
        }
        if ($requestHandler instanceof RequestHandlerInterface) {
            return $requestHandler->handle($request);
        }
        if (is_callable($requestHandler) && is_array($requestHandler)) {
            $requestHandler[0] = $this->container->get($requestHandler[0]);
            $args = $this->getArguments($requestHandler, $request);

            return call_user_func_array($requestHandler, $args);
        }
        if (is_callable($requestHandler)) {
            return call_user_func_array($requestHandler, [$request, $handler]);
        }

        throw new RuntimeException(sprintf('Invalid request handler: %s', gettype($requestHandler)));
    }

    /**
     * Get action arguments.
     */
    protected function getArguments(array $handle, ServerRequestInterface $request): array
    {
        try {
            $return = [];
            $meta = new ReflectionMethod($handle[0], $handle[1]);
            $params = $meta->getParameters();
            $request_params = $request->getAttributes();

            foreach ($params as $param) {
                $type = (string) $param->getType();
                $optional = $param->isOptional();

                if (isset($request_params[$param->name]) && '' !== $request_params[$param->name]) {
                    $param_value = $request_params[$param->name];
                } elseif (true === $optional) {
                    $return[$param->name] = $param->getDefaultValue();

                    continue;
                } else {
                    $param_value = null;
                }

                if ($param->getClass() !== null && $param->getClass()->getName() === ServerRequestInterface::class) {
                    $return[$param->name] = $request;

                    continue;
                }

                if (null !== $param->getClass() && null === $param_value && null !== $this->container) {
                    $return[$param->name] = $this->container->get($type);

                    continue;
                }

                if (null === $param_value && false === $optional) {
                    throw new InvalidArgumentException('misssing required input parameter '.$param->name);
                }

                $return[$param->name] = $this->convertParam(
                    $type,
                    $param_value
                );
            }

            return $return;
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException('misssing or invalid required request parameter');
        }
    }

    /**
     * Convert param.
     */
    protected function convertParam(string $type, $value)
    {
        switch ($type) {
            case 'bool':
                return (bool) $value;

            break;
            case 'int':
                return (int) $value;

            break;
            case 'float':
                return (float) $value;

            break;
            case 'array':
                return (array) $value;

            break;
            default:
                if (class_exists($type) && !($value instanceof $type)) {
                    return new $type($value);
                }

                    return $value;

            break;
        }
    }
}
