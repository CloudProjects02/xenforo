<?php

namespace XF\Finder;

use XF\Entity\ThreadReplyBan;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ThreadReplyBan> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ThreadReplyBan> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ThreadReplyBan|null fetchOne(?int $offset = null)
 * @extends Finder<ThreadReplyBan>
 */
class ThreadReplyBanFinder extends Finder
{
}
