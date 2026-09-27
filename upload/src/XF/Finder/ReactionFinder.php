<?php

namespace XF\Finder;

use XF\Entity\Reaction;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Reaction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Reaction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Reaction|null fetchOne(?int $offset = null)
 * @extends Finder<Reaction>
 */
class ReactionFinder extends Finder
{
}
