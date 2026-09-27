<?php

namespace XF\Finder;

use XF\Entity\AttachmentData;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<AttachmentData> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<AttachmentData> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method AttachmentData|null fetchOne(?int $offset = null)
 * @extends Finder<AttachmentData>
 */
class AttachmentDataFinder extends Finder
{
}
