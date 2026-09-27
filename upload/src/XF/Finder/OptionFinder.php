<?php

namespace XF\Finder;

use XF\Entity\Option;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Option> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Option> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Option|null fetchOne(?int $offset = null)
 * @extends Finder<Option>
 */
class OptionFinder extends Finder
{
}
