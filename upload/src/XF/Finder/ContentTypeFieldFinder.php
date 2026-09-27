<?php

namespace XF\Finder;

use XF\Entity\ContentTypeField;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ContentTypeField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ContentTypeField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ContentTypeField|null fetchOne(?int $offset = null)
 * @extends Finder<ContentTypeField>
 */
class ContentTypeFieldFinder extends Finder
{
}
