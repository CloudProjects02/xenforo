<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFRM\Entity\Category> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\Category> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\Category|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\Category>
 */
class Category extends Finder
{
}
