<?php

namespace XF\Finder;

use XF\Entity\Admin;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Admin> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Admin> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Admin|null fetchOne(?int $offset = null)
 * @extends Finder<Admin>
 */
class AdminFinder extends Finder
{
}
