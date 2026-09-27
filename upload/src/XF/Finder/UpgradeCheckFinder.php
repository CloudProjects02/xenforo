<?php

namespace XF\Finder;

use XF\Entity\UpgradeCheck;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UpgradeCheck> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UpgradeCheck> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UpgradeCheck|null fetchOne(?int $offset = null)
 * @extends Finder<UpgradeCheck>
 */
class UpgradeCheckFinder extends Finder
{
}
