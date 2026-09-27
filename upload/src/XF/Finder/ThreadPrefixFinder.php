<?php

namespace XF\Finder;

use XF\Entity\ThreadPrefix;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadPrefix> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadPrefix> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadPrefix|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadPrefix>
 */
class ThreadPrefixFinder extends Finder
{
}
