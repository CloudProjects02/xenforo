<?php

namespace XF\Finder;

use XF\Entity\HelpPage;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<HelpPage> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<HelpPage> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method HelpPage|null fetchOne(?int $offset = null)
 * @extends Finder<HelpPage>
 */
class HelpPageFinder extends Finder
{
}
