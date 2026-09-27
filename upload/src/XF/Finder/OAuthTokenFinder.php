<?php

namespace XF\Finder;

use XF\Entity\OAuthToken;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OAuthToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OAuthToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OAuthToken|null fetchOne(?int $offset = null)
 * @extends Finder<OAuthToken>
 */
class OAuthTokenFinder extends Finder
{
}
