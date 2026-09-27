<?php

namespace XF\Finder;

use XF\Entity\LikedContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<LikedContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<LikedContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method LikedContent|null fetchOne(?int $offset = null)
 * @extends Finder<LikedContent>
 */
class LikedContentFinder extends Finder
{
}
