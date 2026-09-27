<?php

namespace XF\Finder;

use XF\Entity\ForumWatch;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ForumWatch> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ForumWatch> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ForumWatch|null fetchOne(?int $offset = null)
 * @extends Finder<ForumWatch>
 */
class ForumWatchFinder extends Finder
{
}
