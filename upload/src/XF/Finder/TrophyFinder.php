<?php

namespace XF\Finder;

use XF\Entity\Trophy;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Trophy> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Trophy> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Trophy|null fetchOne(?int $offset = null)
 * @extends Finder<Trophy>
 */
class TrophyFinder extends Finder
{
}
