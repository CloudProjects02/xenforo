<?php

namespace XF\Finder;

use XF\Entity\ApiAttachmentKey;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ApiAttachmentKey> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ApiAttachmentKey> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ApiAttachmentKey|null fetchOne(?int $offset = null)
 * @extends Finder<ApiAttachmentKey>
 */
class ApiAttachmentKeyFinder extends Finder
{
}
