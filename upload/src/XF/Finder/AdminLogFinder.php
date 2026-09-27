<?php

namespace XF\Finder;

use XF\Entity\AdminLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AdminLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AdminLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AdminLog|null fetchOne(?int $offset = null)
 * @extends Finder<AdminLog>
 */
class AdminLogFinder extends Finder
{
}
