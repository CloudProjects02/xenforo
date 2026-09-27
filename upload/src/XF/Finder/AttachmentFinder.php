<?php

namespace XF\Finder;

use XF\Entity\Attachment;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Attachment> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Attachment> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Attachment|null fetchOne(?int $offset = null)
 * @extends Finder<Attachment>
 */
class AttachmentFinder extends Finder
{
}
