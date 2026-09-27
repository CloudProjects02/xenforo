<?php

namespace XF\Finder;

use XF\Entity\Moderator;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Moderator> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Moderator> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Moderator|null fetchOne(?int $offset = null)
 * @extends Finder<Moderator>
 */
class ModeratorFinder extends Finder
{
}
