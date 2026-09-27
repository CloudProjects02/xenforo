<?php

namespace XF\Finder;

use XF\Entity\ModeratorLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ModeratorLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ModeratorLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ModeratorLog|null fetchOne(?int $offset = null)
 * @extends Finder<ModeratorLog>
 */
class ModeratorLogFinder extends Finder
{
}
