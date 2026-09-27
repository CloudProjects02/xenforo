<?php

namespace XF\Finder;

use XF\Entity\PermissionInterfaceGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PermissionInterfaceGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PermissionInterfaceGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PermissionInterfaceGroup|null fetchOne(?int $offset = null)
 * @extends Finder<PermissionInterfaceGroup>
 */
class PermissionInterfaceGroupFinder extends Finder
{
}
