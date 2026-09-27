<?php

namespace XF\Finder;

use XF\Entity\PermissionCacheContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PermissionCacheContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PermissionCacheContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PermissionCacheContent|null fetchOne(?int $offset = null)
 * @extends Finder<PermissionCacheContent>
 */
class PermissionCacheContentFinder extends Finder
{
}
