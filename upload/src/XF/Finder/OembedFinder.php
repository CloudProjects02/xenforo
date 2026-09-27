<?php

namespace XF\Finder;

use XF\Entity\Oembed;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Oembed> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Oembed> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Oembed|null fetchOne(?int $offset = null)
 * @extends Finder<Oembed>
 */
class OembedFinder extends Finder
{
}
