<?php

namespace XF\Finder;

use XF\Entity\Forum;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Forum> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Forum> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Forum|null fetchOne(?int $offset = null)
 * @extends Finder<Forum>
 */
class ForumFinder extends Finder
{
}
