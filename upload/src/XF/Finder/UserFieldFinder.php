<?php

namespace XF\Finder;

use XF\Entity\UserField;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserField|null fetchOne(?int $offset = null)
 * @extends Finder<UserField>
 */
class UserFieldFinder extends Finder
{
}
