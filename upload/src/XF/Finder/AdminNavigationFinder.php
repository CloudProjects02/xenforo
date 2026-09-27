<?php

namespace XF\Finder;

use XF\Entity\AdminNavigation;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AdminNavigation> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AdminNavigation> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AdminNavigation|null fetchOne(?int $offset = null)
 * @extends Finder<AdminNavigation>
 */
class AdminNavigationFinder extends Finder
{
}
