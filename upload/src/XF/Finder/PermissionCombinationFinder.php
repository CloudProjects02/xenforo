<?php

namespace XF\Finder;

use XF\Entity\PermissionCombination;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PermissionCombination> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PermissionCombination> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PermissionCombination|null fetchOne(?int $offset = null)
 * @extends Finder<PermissionCombination>
 */
class PermissionCombinationFinder extends Finder
{
}
