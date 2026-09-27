<?php

namespace XF\Finder;

use XF\Entity\EmailBounceLog;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<EmailBounceLog> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<EmailBounceLog> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method EmailBounceLog|null fetchOne(?int $offset = null)
 * @extends Finder<EmailBounceLog>
 */
class EmailBounceLogFinder extends Finder
{
}
