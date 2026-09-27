<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\LotteryHistory> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\LotteryHistory> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\LotteryHistory|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\LotteryHistory>
 */
class LotteryHistoryFinder extends Finder
{
}