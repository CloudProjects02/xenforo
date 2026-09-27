<?php

namespace XF\Finder;

use XF\Entity\WidgetDefinition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<WidgetDefinition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<WidgetDefinition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method WidgetDefinition|null fetchOne(?int $offset = null)
 * @extends Finder<WidgetDefinition>
 */
class WidgetDefinitionFinder extends Finder
{
}
