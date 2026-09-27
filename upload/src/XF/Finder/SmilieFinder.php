<?php

namespace XF\Finder;

use XF\Entity\Smilie;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Smilie> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Smilie> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Smilie|null fetchOne(?int $offset = null)
 * @extends Finder<Smilie>
 */
class SmilieFinder extends Finder
{
}
