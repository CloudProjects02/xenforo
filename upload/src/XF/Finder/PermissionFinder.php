<?php

namespace XF\Finder;

use XF\Entity\Permission;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Permission> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Permission> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Permission|null fetchOne(?int $offset = null)
 * @extends Finder<Permission>
 */
class PermissionFinder extends Finder
{
}
