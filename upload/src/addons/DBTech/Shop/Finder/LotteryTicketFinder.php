<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\LotteryTicket> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\LotteryTicket> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\LotteryTicket|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\LotteryTicket>
 */
class LotteryTicketFinder extends Finder
{
}