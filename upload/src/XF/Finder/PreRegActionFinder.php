<?php

namespace XF\Finder;

use XF\Entity\PreRegAction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<PreRegAction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<PreRegAction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method PreRegAction|null fetchOne(?int $offset = null)
 * @extends Finder<PreRegAction>
 */
class PreRegActionFinder extends Finder
{
}
