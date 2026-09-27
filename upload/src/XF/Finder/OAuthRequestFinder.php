<?php

namespace XF\Finder;

use XF\Entity\OAuthRequest;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OAuthRequest> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OAuthRequest> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OAuthRequest|null fetchOne(?int $offset = null)
 * @extends Finder<OAuthRequest>
 */
class OAuthRequestFinder extends Finder
{
}
