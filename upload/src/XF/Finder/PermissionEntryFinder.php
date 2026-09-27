<?php

namespace XF\Finder;

use XF\Entity\PermissionEntry;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PermissionEntry> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PermissionEntry> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PermissionEntry|null fetchOne(?int $offset = null)
 * @extends Finder<PermissionEntry>
 */
class PermissionEntryFinder extends Finder
{
}
