<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Resource\AbstractResource;
use Balloon\Resource\AttributeResolver;
use Balloon\User\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

class User extends AbstractResource implements UserInterface
{
    /**
     * Kind.
     */
    public const KIND = 'User';

    /**
     * Initialize.
     */
    public function __construct(array $resource = [])
    {
        $this->resource = $resource;
    }

    /**
     * Validate password.
     */
    public function validatePassword(string $password): bool
    {
        return password_verify($password, $this->resource['hash']);
    }

    /**
     * Decorate.
     */
    public function decorate(ServerRequestInterface $request): array
    {
        /*$resource = [
            'data' => $this->getData(),
        ];*/

        return AttributeResolver::resolve($request, $this, []);
    }
}
