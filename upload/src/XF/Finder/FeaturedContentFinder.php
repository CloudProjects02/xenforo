<?php

namespace XF\Finder;

use XF\Entity\FeaturedContent;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<FeaturedContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<FeaturedContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method FeaturedContent|null fetchOne(?int $offset = null)
 * @extends Finder<FeaturedContent>
 */
class FeaturedContentFinder extends Finder
{
}
