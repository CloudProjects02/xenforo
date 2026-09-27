<?php

namespace XF\Finder;

use XF\Entity\UserGroupPromotion;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserGroupPromotion> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserGroupPromotion> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserGroupPromotion|null fetchOne(?int $offset = null)
 * @extends Finder<UserGroupPromotion>
 */
class UserGroupPromotionFinder extends Finder
{
}
