<?php

namespace XF\Finder;

use XF\Entity\ApprovalQueue;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ApprovalQueue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ApprovalQueue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ApprovalQueue|null fetchOne(?int $offset = null)
 * @extends Finder<ApprovalQueue>
 */
class ApprovalQueueFinder extends Finder
{
}
