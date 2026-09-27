<?php

namespace XF\Finder;

use XF\Entity\CodeEventListener;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<CodeEventListener> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<CodeEventListener> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method CodeEventListener|null fetchOne(?int $offset = null)
 * @extends Finder<CodeEventListener>
 */
class CodeEventListenerFinder extends Finder
{
}
