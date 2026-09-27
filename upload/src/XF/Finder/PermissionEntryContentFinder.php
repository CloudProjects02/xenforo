<?php

namespace XF\Finder;

use XF\Entity\PermissionEntryContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PermissionEntryContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PermissionEntryContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PermissionEntryContent|null fetchOne(?int $offset = null)
 * @extends Finder<PermissionEntryContent>
 */
class PermissionEntryContentFinder extends Finder
{
}
