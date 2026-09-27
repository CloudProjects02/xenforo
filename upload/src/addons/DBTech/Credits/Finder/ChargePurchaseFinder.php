<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Credits\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Credits\Entity\ChargePurchase> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Credits\Entity\ChargePurchase> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Credits\Entity\ChargePurchase|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Credits\Entity\ChargePurchase>
 */
class ChargePurchaseFinder extends Finder
{
}