<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Wopi\Session;

use Balloon\Filesystem\Node\AbstractNode;
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
     * Get wopi url.
     */
    public function getWopiUrl(): string
    {
        return '/api/v2/office/wopi/files/'.$this->file->getId().'?access_token='.$this->getAccessToken().'&access_token_ttl='.$this->getAccessTokenTTl();
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
            'ClientUrl' => null,
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
            //'ReadOnly' => $this->file->mayWrite(),
            'RestrictedWebViewOnly' => false,
            'SHA256' => null,
            'SignoutUrl' => null,
            'Size' => $this->file->getSize(),
            'SupportsCoauth' => false,
            'SupportsCobalt' => false,
            'SupportsFolders' => true,
            'SupportsLocks' => true,
            'SupportsGetLock' => true,
            'SupportsScenarioLinks' => false,
            'SupportsSecureStore' => false,
            'SupportsUpdate' => true,
            'SupportsRename' => true,
            'FileNameMaxLength' => AbstractNode::MAX_NAME_LENGTH,
            'TenantId' => null,
            'TermsOfUseUrl' => null,
            'TimeZone' => null,
            'UserCanAttend' => false,
            'UserCanNotWriteRelative' => false,
            'UserCanPresent' => false,
            'UserCanRename' => true,
            'UserCanWrite' => true,
            //'UserCanWrite' => $this->file->mayWrite(),
            'UserFriendlyName' => $this->user->getUsername(),
            'UserId' => (string) $this->user->getId(),
            'Version' => (string) $this->file->getVersion(),
            'WebEditingDisabled' => false,
        ];

        return $attributes;
    }
}
