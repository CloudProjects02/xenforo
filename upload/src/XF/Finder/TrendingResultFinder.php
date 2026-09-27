<?php

namespace XF\Finder;

use XF\Entity\TrendingResult;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TrendingResult> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TrendingResult> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TrendingResult|null fetchOne(?int $offset = null)
 * @extends Finder<TrendingResult>
 */
class TrendingResultFinder extends Finder
{
}
