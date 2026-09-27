<?php

namespace XF\Finder;

use XF\Entity\FindNew;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<FindNew> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<FindNew> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method FindNew|null fetchOne(?int $offset = null)
 * @extends Finder<FindNew>
 */
class FindNewFinder extends Finder
{
}
