<?php

namespace XF\Finder;

use XF\Entity\ChangeLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ChangeLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ChangeLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ChangeLog|null fetchOne(?int $offset = null)
 * @extends Finder<ChangeLog>
 */
class ChangeLogFinder extends Finder
{
}
