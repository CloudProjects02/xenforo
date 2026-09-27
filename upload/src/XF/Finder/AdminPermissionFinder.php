<?php

namespace XF\Finder;

use XF\Entity\AdminPermission;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AdminPermission> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AdminPermission> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AdminPermission|null fetchOne(?int $offset = null)
 * @extends Finder<AdminPermission>
 */
class AdminPermissionFinder extends Finder
{
}
