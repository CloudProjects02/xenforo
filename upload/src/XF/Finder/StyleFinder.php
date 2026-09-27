<?php

namespace XF\Finder;

use XF\Entity\Style;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Style> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Style> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Style|null fetchOne(?int $offset = null)
 * @extends Finder<Style>
 */
class StyleFinder extends Finder
{
}
