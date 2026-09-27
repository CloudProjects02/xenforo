<?php

namespace XF\Finder;

use XF\Entity\AddOn;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AddOn> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AddOn> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AddOn|null fetchOne(?int $offset = null)
 * @extends Finder<AddOn>
 */
class AddOnFinder extends Finder
{
}
