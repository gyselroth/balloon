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

class Helper
{
    /**
     * Entrypoint.
     */
    public static function getAll(ServerRequestInterface $request, UserInterface $identity, Acl $acl, iterable $cursor): ResponseInterface
    {
        $query = $request->getQueryParams();

        if (isset($query['watch']) && $query['watch'] !== 'false' && !empty($query['watch'])) {
            return self::watchAll($request, $identity, $acl, $cursor);
        }

        if (isset($query['stream']) && $query['stream'] !== 'false' && !empty($query['stream'])) {
            return self::stream($request, $identity, $acl, $cursor);
        }

        $body = $acl->filterOutput($request, $identity, $cursor);
        $body = Pager::fromRequest($body, $request);

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_OK),
            $body,
            ['pretty' => isset($query['pretty'])]
        );
    }

    /**
     * Entrypoint.
     */
    public static function getOne(ServerRequestInterface $request, User $identity, ResourceInterface $resource): ResponseInterface
    {
        $query = $request->getQueryParams();

        return new UnformattedResponse(
            (new Response())->withStatus(StatusCodeInterface::STATUS_OK),
            $resource->decorate($request),
            ['pretty' => isset($query['pretty'])]
        );
    }

    /**
     * Watch.
     */
    public static function stream(ServerRequestInterface $request, Identity $identity, Acl $acl, iterable $cursor): ResponseInterface
    {
        //Stream is valid for 5min, after a new requests needs to be sent
        ini_set('max_execution_time', '300');

        $query = $request->getQueryParams();
        $options = isset($query['pretty']) ? JSON_PRETTY_PRINT : 0;

        $stream = new StreamIterator($cursor, function ($resource) use ($request, $options) {
            if ($this->tell() === 0) {
                echo  '[';
            } else {
                echo  ',';
            }

            echo json_encode($resource->decorate($request), $options);

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
    public static function watchAll(ServerRequestInterface $request, Identity $identity, Acl $acl, iterable $cursor): ResponseInterface
    {
        //Watcher is valid for 5min, after a new requests needs to be sent
        ini_set('max_execution_time', '300');

        $query = $request->getQueryParams();
        $options = isset($query['pretty']) ? JSON_PRETTY_PRINT : 0;

        $stream = new StreamIterator($cursor, function ($event) use ($request, $options) {
            if ($this->tell() === 0) {
                echo  '[';
            } else {
                echo  ',';
            }

            echo json_encode([
                $event[0],
                $event[1]->decorate($request),
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
