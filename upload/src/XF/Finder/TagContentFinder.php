<?php

namespace XF\Finder;

use XF\Entity\TagContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<TagContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<TagContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method TagContent|null fetchOne(?int $offset = null)
 * @extends Finder<TagContent>
 */
class TagContentFinder extends Finder
{
}
