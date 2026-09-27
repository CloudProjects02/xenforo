<?php

namespace XF\Finder;

use XF\Entity\StyleProperty;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<StyleProperty> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<StyleProperty> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method StyleProperty|null fetchOne(?int $offset = null)
 * @extends Finder<StyleProperty>
 */
class StylePropertyFinder extends Finder
{
}
