<?php

namespace XF\Finder;

use XF\Entity\ActivitySummaryDefinition;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ActivitySummaryDefinition> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ActivitySummaryDefinition> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ActivitySummaryDefinition|null fetchOne(?int $offset = null)
 * @extends Finder<ActivitySummaryDefinition>
 */
class ActivitySummaryDefinitionFinder extends Finder
{
}
