<?php

namespace XF\Finder;

use XF\Entity\Tag;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Tag> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Tag> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Tag|null fetchOne(?int $offset = null)
 * @extends Finder<Tag>
 */
class TagFinder extends Finder
{
}
