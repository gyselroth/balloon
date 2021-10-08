<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copyright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Session;

use Balloon\Filesystem\Node\File;
use Balloon\Server\User;

interface SessionInterface
{
    /**
     * Get user.
     */
    public function getUser(): User;

    /**
     * Get File.
     */
    public function getFile(): File;

    /**
     * Get valid until.
     */
    public function getAccessTokenTTl(): int;

    /**
     * Get access token.
     */
    public function getAccessToken(): string;

    /**
     * Get session attributes.
     */
    public function getAttributes(): array;
}
