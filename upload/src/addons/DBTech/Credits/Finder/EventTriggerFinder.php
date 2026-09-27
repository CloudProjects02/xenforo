<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Credits\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Credits\Entity\EventTrigger> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Credits\Entity\EventTrigger> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Credits\Entity\EventTrigger|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Credits\Entity\EventTrigger>
 */
class EventTriggerFinder extends Finder
{
}