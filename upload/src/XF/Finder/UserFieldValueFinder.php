<?php

namespace XF\Finder;

use XF\Entity\UserFieldValue;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserFieldValue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserFieldValue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserFieldValue|null fetchOne(?int $offset = null)
 * @extends Finder<UserFieldValue>
 */
class UserFieldValueFinder extends Finder
{
}
