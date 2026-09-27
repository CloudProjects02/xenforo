<?php

namespace XF\Finder;

use XF\Entity\OAuthCode;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OAuthCode> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OAuthCode> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OAuthCode|null fetchOne(?int $offset = null)
 * @extends Finder<OAuthCode>
 */
class OAuthCodeFinder extends Finder
{
}
