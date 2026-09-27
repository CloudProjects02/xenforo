<?php

namespace XF\Finder;

use XF\Entity\Navigation;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Navigation> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Navigation> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Navigation|null fetchOne(?int $offset = null)
 * @extends Finder<Navigation>
 */
class NavigationFinder extends Finder
{
}
