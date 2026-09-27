<?php

namespace XF\Finder;

use XF\Entity\Notice;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Notice> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Notice> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Notice|null fetchOne(?int $offset = null)
 * @extends Finder<Notice>
 */
class NoticeFinder extends Finder
{
}
