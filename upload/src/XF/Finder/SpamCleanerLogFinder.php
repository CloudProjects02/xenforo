<?php

namespace XF\Finder;

use XF\Entity\SpamCleanerLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<SpamCleanerLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<SpamCleanerLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method SpamCleanerLog|null fetchOne(?int $offset = null)
 * @extends Finder<SpamCleanerLog>
 */
class SpamCleanerLogFinder extends Finder
{
}
