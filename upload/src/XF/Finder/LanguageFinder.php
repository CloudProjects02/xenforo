<?php

namespace XF\Finder;

use XF\Entity\Language;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<Language> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<Language> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method Language|null fetchOne(?int $offset = null)
 * @extends Finder<Language>
 */
class LanguageFinder extends Finder
{
}
