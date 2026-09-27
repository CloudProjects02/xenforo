<?php

namespace XF\Finder;

use XF\Entity\FindNewDefault;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<FindNewDefault> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<FindNewDefault> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method FindNewDefault|null fetchOne(?int $offset = null)
 * @extends Finder<FindNewDefault>
 */
class FindNewDefaultFinder extends Finder
{
}
