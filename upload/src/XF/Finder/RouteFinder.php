<?php

namespace XF\Finder;

use XF\Entity\Route;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Route> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Route> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Route|null fetchOne(?int $offset = null)
 * @extends Finder<Route>
 */
class RouteFinder extends Finder
{
}
