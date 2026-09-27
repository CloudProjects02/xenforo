<?php

namespace XF\Finder;

use XF\Entity\Widget;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Widget> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Widget> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Widget|null fetchOne(?int $offset = null)
 * @extends Finder<Widget>
 */
class WidgetFinder extends Finder
{
}
