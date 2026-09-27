<?php

namespace XF\Finder;

use XF\Entity\UserConnectedAccount;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<UserConnectedAccount> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<UserConnectedAccount> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method UserConnectedAccount|null fetchOne(?int $offset = null)
 * @extends Finder<UserConnectedAccount>
 */
class UserConnectedAccountFinder extends Finder
{
}
