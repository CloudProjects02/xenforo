<?php

namespace XF\Finder;

use XF\Entity\ApiKey;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ApiKey> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ApiKey> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ApiKey|null fetchOne(?int $offset = null)
 * @extends Finder<ApiKey>
 */
class ApiKeyFinder extends Finder
{
}
