<?php

namespace XF\Finder;

use XF\Entity\Page;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Page> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Page> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Page|null fetchOne(?int $offset = null)
 * @extends Finder<Page>
 */
class PageFinder extends Finder
{
}
