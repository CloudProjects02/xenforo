<?php

namespace XF\Finder;

use XF\Entity\ErrorLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ErrorLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ErrorLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ErrorLog|null fetchOne(?int $offset = null)
 * @extends Finder<ErrorLog>
 */
class ErrorLogFinder extends Finder
{
}
