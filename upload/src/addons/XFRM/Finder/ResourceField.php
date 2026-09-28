<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFRM\Entity\ResourceField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\ResourceField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\ResourceField|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\ResourceField>
 */
class ResourceField extends Finder
{
}
