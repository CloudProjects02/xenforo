<?php

namespace XF\Finder;

use XF\Entity\BookmarkItem;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<BookmarkItem> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<BookmarkItem> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method BookmarkItem|null fetchOne(?int $offset = null)
 * @extends Finder<BookmarkItem>
 */
class BookmarkItemFinder extends Finder
{
}
