<?php

namespace XF\Finder;

use XF\Entity\BanEmail;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<BanEmail> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<BanEmail> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method BanEmail|null fetchOne(?int $offset = null)
 * @extends Finder<BanEmail>
 */
class BanEmailFinder extends Finder
{
}
