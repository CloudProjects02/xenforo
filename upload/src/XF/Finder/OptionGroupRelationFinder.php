<?php

namespace XF\Finder;

use XF\Entity\OptionGroupRelation;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<OptionGroupRelation> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<OptionGroupRelation> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method OptionGroupRelation|null fetchOne(?int $offset = null)
 * @extends Finder<OptionGroupRelation>
 */
class OptionGroupRelationFinder extends Finder
{
}
