<?php

namespace XF\Finder;

use XF\Entity\ApiLoginToken;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ApiLoginToken> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ApiLoginToken> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ApiLoginToken|null fetchOne(?int $offset = null)
 * @extends Finder<ApiLoginToken>
 */
class ApiLoginTokenFinder extends Finder
{
}
