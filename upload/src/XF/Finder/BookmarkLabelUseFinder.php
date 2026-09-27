<?php

namespace XF\Finder;

use XF\Entity\BookmarkLabelUse;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<BookmarkLabelUse> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<BookmarkLabelUse> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method BookmarkLabelUse|null fetchOne(?int $offset = null)
 * @extends Finder<BookmarkLabelUse>
 */
class BookmarkLabelUseFinder extends Finder
{
}
