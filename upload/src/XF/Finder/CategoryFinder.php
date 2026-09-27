<?php

namespace XF\Finder;

use XF\Entity\Category;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Category> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Category> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Category|null fetchOne(?int $offset = null)
 * @extends Finder<Category>
 */
class CategoryFinder extends Finder
{
}
