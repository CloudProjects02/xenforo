<?php

namespace XF\Finder;

use XF\Entity\SmilieCategory;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SmilieCategory> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SmilieCategory> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SmilieCategory|null fetchOne(?int $offset = null)
 * @extends Finder<SmilieCategory>
 */
class SmilieCategoryFinder extends Finder
{
}
