<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFRM\Entity\ResourceFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\ResourceFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\ResourceFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\ResourceFieldValue>
 */
class ResourceFieldValue extends Finder
{
}
