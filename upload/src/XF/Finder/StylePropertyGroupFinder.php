<?php

namespace XF\Finder;

use XF\Entity\StylePropertyGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<StylePropertyGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<StylePropertyGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method StylePropertyGroup|null fetchOne(?int $offset = null)
 * @extends Finder<StylePropertyGroup>
 */
class StylePropertyGroupFinder extends Finder
{
}
