<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Rest;

use Balloon\Acl;
use Balloon\Resource\ResourceInterface;
use Balloon\User;
use Balloon\User\UserInterface;
use Fig\Http\Message\StatusCodeInterface;
use Lcobucci\ContentNegotiation\UnformattedResponse;
use Micro\Auth\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StreamIterator\StreamIterator;
use Zend\Diactoros\Response;
use Rs\Json\Patch;
use function MongoDB\BSON\fromJSON;
use function MongoDB\BSON\toPHP;

class Helper
{
    /**
     * Patcher
     */
    public static function patch(ServerRequestInterface $request, ResourceInterface $resource): array
    {
        $type = $request->getHeaders()['content-type'][0] ?? 'application/json';
        $doc = $resource->toArray();

        if($type === 'application/json-patch+json') {
            $patch = new Patch(json_encode($doc), $request->getBody()->getContents());
            $patched = $patch->apply();
            return (array)toPHP(fromJSON($patched));
        }

        $body = $request->getParsedBody();
        return array_replace_recursive($doc, $body);
    }

    /**
     * Entrypoint.
     */
    public static function getAll(ServerRequestInterface $request, UserInterface $user, Acl $acl, iterable $cursor, ModelFactoryInterface $model_factory): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch']) && $query['watch'] !== 'false' && !empty($query['watch'])) {
            return self::watchAll($request, $user, $acl, $cursor, $model_factory);
        }

        if (isset($query['stream']) && $query['stream'] !== 'false' && !empty($query['stream'])) {
            return self::stream($request, $user, $acl, $cursor, $model_factory);
        }

        $body = $acl->filterOutput($request, $user, $cursor);
        $body = Pager::fromRequest($body, $request, $model_factory);
        return self::response($request, $body);
    }

    /**
     * Response
     */
    public static function response(ServerRequestInterface $request, $payload, int $status=StatusCodeInterface::STATUS_OK)
    {
        $query = $request->getQueryParams();

        return new UnformattedResponse(
            (new Response())->withStatus($status),
            $payload,
            ['pretty' => isset($query['pretty'])]
        );
    }

    /**
     * Entrypoint.
     */
    public static function getOne(ServerRequestInterface $request, UserInterface $user, ResourceInterface $resource, ModelFactoryInterface $model_factory, int $status=StatusCodeInterface::STATUS_OK): ResponseInterface
    {
        return self::response($request, $model_factory->decorate($resource, $request), $status);
    }

    /**
     * Watch.
     */
    public static function stream(ServerRequestInterface $request, UserInterface $user, Acl $acl, iterable $cursor, ModelFactoryInterface $model_factory): ResponseInterface
    {
        //Stream is valid for 5min, after a new requests needs to be sent
        ini_set('max_execution_time', '300');

        $query = $request->getQueryParams();
        $options = isset($query['pretty']) ? JSON_PRETTY_PRINT : 0;

        $stream = new StreamIterator($cursor, function ($resource) use ($model_factory, $request, $options) {
            if ($this->tell() === 0) {
                echo  '[';
            } else {
                echo  ',';
            }

            echo json_encode($model_factory->decorate($resource, $request), $options);

            if ($this->eof()) {
                echo ']';
            }

            flush();
        });

        return (new Response($stream))
            ->withHeader('X-Accel-Buffering', 'no')
            ->withHeader('Content-Type', 'application/json;stream')
            ->withStatus(StatusCodeInterface::STATUS_OK);
    }

    /**
     * Watch.
     */
    public static function watchAll(ServerRequestInterface $request, UserInterface $user, Acl $acl, iterable $cursor, ModelFactoryInterface $model_factory): ResponseInterface
    {
        //Watcher is valid for 5min, after a new requests needs to be sent
        ini_set('max_execution_time', '300');

        $query = $request->getQueryParams();
        $options = isset($query['pretty']) ? JSON_PRETTY_PRINT : 0;

        $stream = new StreamIterator($cursor, function ($event) use ($model_factory, $request, $options) {
            if ($this->tell() === 0) {
                echo  '[';
            } else {
                echo  ',';
            }

            echo json_encode([
                $event[0],
                $model_factory->decorate($event[1], $request),
            ], $options);

            if ($this->eof()) {
                echo ']';
            }

            flush();
        });

        return (new Response($stream))
            ->withHeader('X-Accel-Buffering', 'no')
            ->withHeader('Content-Type', 'application/json;stream=watch')
            ->withStatus(StatusCodeInterface::STATUS_OK);
    }
}
