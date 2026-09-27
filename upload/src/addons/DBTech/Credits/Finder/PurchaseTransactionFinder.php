<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Credits\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Credits\Entity\PurchaseTransaction> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Credits\Entity\PurchaseTransaction> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Credits\Entity\PurchaseTransaction|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Credits\Entity\PurchaseTransaction>
 */
class PurchaseTransactionFinder extends Finder
{
}