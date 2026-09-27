<?php

namespace XF\Finder;

use XF\Entity\OAuthClient;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OAuthClient> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OAuthClient> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OAuthClient|null fetchOne(?int $offset = null)
 * @extends Finder<OAuthClient>
 */
class OAuthClientFinder extends Finder
{
}
