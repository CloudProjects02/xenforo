<?php

namespace XF\Finder;

use XF\Entity\DeletionLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<DeletionLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<DeletionLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method DeletionLog|null fetchOne(?int $offset = null)
 * @extends Finder<DeletionLog>
 */
class DeletionLogFinder extends Finder
{
}
