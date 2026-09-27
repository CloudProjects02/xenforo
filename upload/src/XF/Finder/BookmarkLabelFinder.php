<?php

namespace XF\Finder;

use XF\Entity\BookmarkLabel;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<BookmarkLabel> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<BookmarkLabel> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method BookmarkLabel|null fetchOne(?int $offset = null)
 * @extends Finder<BookmarkLabel>
 */
class BookmarkLabelFinder extends Finder
{
}
