<?php

namespace XF\Finder;

use XF\Entity\ForumRead;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ForumRead> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ForumRead> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ForumRead|null fetchOne(?int $offset = null)
 * @extends Finder<ForumRead>
 */
class ForumReadFinder extends Finder
{
}
