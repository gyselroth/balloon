<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Storage;

use Balloon\Server\User;
use Micro\Auth\Adapter\Basic\BasicInterface;

class UserCredentialsMultiFactor extends UserCredentials
{
    /**
     * {@inheritdoc}
     */
    public function checkUserCredentials($username, $password)
    {
        foreach ($this->auth->getAdapters() as $adapter) {
            if ($adapter instanceof BasicInterface) {
                if ($adapter->plainAuth($username, $password) === true) {
                    $user = null;
                    $identity = $this->auth->createIdentity($adapter);

                    try {
                        $user = $this->server->getUserByName($username);
                    } catch (User\Exception\NotFound $e) {
                        $this->logger->warning('failed connect authenticated user, user account does not exists', [
                            'category' => get_class($this),
                        ]);
                    }

                    if (!($user instanceof User)) {
                        throw new User\Exception\NotAuthenticated('user does not exists', User\Exception\NotAuthenticated::USER_NOT_FOUND);
                    }

                    if ($user->isDeleted()) {
                        throw new User\Exception\NotAuthenticated(
                            'user is disabled and can not be used',
                            User\Exception\NotAuthenticated::USER_DELETED
                        );
                    }

                    return true;
                }
            }
        }

        return false;
    }
}
