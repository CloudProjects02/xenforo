<?php

namespace XF\Finder;

use XF\Entity\WarningActionTrigger;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<WarningActionTrigger> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<WarningActionTrigger> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method WarningActionTrigger|null fetchOne(?int $offset = null)
 * @extends Finder<WarningActionTrigger>
 */
class WarningActionTriggerFinder extends Finder
{
}
