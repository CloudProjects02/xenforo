<?php

namespace XF\Finder;

use XF\Entity\CronEntry;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CronEntry> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CronEntry> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CronEntry|null fetchOne(?int $offset = null)
 * @extends Finder<CronEntry>
 */
class CronEntryFinder extends Finder
{
}
