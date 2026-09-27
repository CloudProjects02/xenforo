<?php

namespace XF\Finder;

use XF\Entity\StylePropertyMap;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<StylePropertyMap> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<StylePropertyMap> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method StylePropertyMap|null fetchOne(?int $offset = null)
 * @extends Finder<StylePropertyMap>
 */
class StylePropertyMapFinder extends Finder
{
}
