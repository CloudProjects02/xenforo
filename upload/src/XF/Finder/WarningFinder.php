<?php

namespace XF\Finder;

use XF\Entity\Warning;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Warning> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Warning> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Warning|null fetchOne(?int $offset = null)
 * @extends Finder<Warning>
 */
class WarningFinder extends Finder
{
}
