<?php

namespace XF\Finder;

use XF\Entity\WarningAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<WarningAction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<WarningAction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method WarningAction|null fetchOne(?int $offset = null)
 * @extends Finder<WarningAction>
 */
class WarningActionFinder extends Finder
{
}
