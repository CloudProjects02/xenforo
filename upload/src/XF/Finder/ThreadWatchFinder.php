<?php

namespace XF\Finder;

use XF\Entity\ThreadWatch;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadWatch> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadWatch> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadWatch|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadWatch>
 */
class ThreadWatchFinder extends Finder
{
}
