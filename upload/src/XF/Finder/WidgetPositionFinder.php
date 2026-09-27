<?php

namespace XF\Finder;

use XF\Entity\WidgetPosition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<WidgetPosition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<WidgetPosition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method WidgetPosition|null fetchOne(?int $offset = null)
 * @extends Finder<WidgetPosition>
 */
class WidgetPositionFinder extends Finder
{
}
