<?php

namespace XF\Finder;

use XF\Entity\WarningDefinition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<WarningDefinition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<WarningDefinition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method WarningDefinition|null fetchOne(?int $offset = null)
 * @extends Finder<WarningDefinition>
 */
class WarningDefinitionFinder extends Finder
{
}
