<?php

namespace XF\Finder;

use XF\Entity\OptionGroup;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OptionGroup> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OptionGroup> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OptionGroup|null fetchOne(?int $offset = null)
 * @extends Finder<OptionGroup>
 */
class OptionGroupFinder extends Finder
{
}
