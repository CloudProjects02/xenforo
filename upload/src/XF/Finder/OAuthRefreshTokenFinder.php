<?php

namespace XF\Finder;

use XF\Entity\OAuthRefreshToken;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OAuthRefreshToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OAuthRefreshToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OAuthRefreshToken|null fetchOne(?int $offset = null)
 * @extends Finder<OAuthRefreshToken>
 */
class OAuthRefreshTokenFinder extends Finder
{
}
