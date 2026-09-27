<?php

namespace XF\Finder;

use XF\Entity\EditHistory;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<EditHistory> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<EditHistory> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method EditHistory|null fetchOne(?int $offset = null)
 * @extends Finder<EditHistory>
 */
class EditHistoryFinder extends Finder
{
}
