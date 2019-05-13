<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Session;

use Balloon\Filesystem\Node\File;
use Balloon\Server\User;

class Session implements SessionInterface
{
    /**
     * Session.
     *
     * @var array
     */
    protected $session = [];

    /**
     * File.
     *
     * @var File
     */
    protected $file;

    /**
     * User.
     *
     * @var User
     */
    protected $user;

    /**
     * Session.
     */
    public function __construct(File $file, User $user, array $session)
    {
        $this->file = $file;
        $this->user = $user;
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(): File
    {
        return $this->File;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenTTl(): int
    {
        return $this->session['ttl']->toDateTime()->format('U') * 1000;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessToken(): string
    {
        return $this->session['token'];
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes(): array
    {
        $attrs = $this->file->getAttributes(['name', 'version']);
        $attributes = [
            'AllowExternalMarketplace' => false,
            'BaseFileName' => $this->file->getName(),
            'BreadcrumbBrandName' => null,
            'BreadcrumbBrandUrl' => null,
            'BreadcrumbDocName' => null,
            'BreadcrumbDocUrl' => null,
            'BreadcrumbFolderName' => null,
            'BreadcrumbFolderUrl' => null,
            'ClientUrl' => 'https://localhost:8084/webdav',
            'CloseButtonClosesWindow' => false,
            'CloseUrl' => null,
            'DisableBrowserCachingOfUserContent' => false,
            'DisablePrint' => false,
            'DisableTranslation' => false,
            'DownloadUrl' => null,
            'FileSharingUrl' => null,
            'FileUrl' => null,
            'HostAuthenticationId' => null,
            'HostEditUrl' => null,
            'HostEmbeddedEditUrl' => null,
            'HostEmbeddedViewUrl' => null,
            'HostName' => null,
            'HostNotes' => null,
            'HostRestUrl' => null,
            'HostViewUrl' => null,
            'IrmPolicyDescription' => null,
            'IrmPolicyTitle' => null,
            'OwnerId' => (string) $this->file->getOwner(),
            'PresenceProvider' => null,
            'PresenceUserId' => null,
            'PrivacyUrl' => null,
            'ProtectInClient' => false,
            'ReadOnly' => false,
            'RestrictedWebViewOnly' => false,
            'SHA256' => null,
            'SignoutUrl' => null,
            'Size' => $this->file->getSize(),
            'SupportsCoauth' => false,
            'SupportsCobalt' => false,
            'SupportsFolders' => true,
            'SupportsLocks' => true,
            'SupportsScenarioLinks' => false,
            'SupportsSecureStore' => false,
            'SupportsUpdate' => true,
            'TenantId' => null,
            'TermsOfUseUrl' => null,
            'TimeZone' => null,
            'UserCanAttend' => false,
            'UserCanNotWriteRelative' => false,
            'UserCanPresent' => false,
            'UserCanWrite' => true,
            'UserFriendlyName' => $this->user->getUsername(),
            'UserId' => (string) $this->user->getId(),
            'Version' => $this->file->getVersion(),
            'WebEditingDisabled' => false,
        ];

        return $attributes;
    }
}
