<?php

namespace XF\Finder;

use XF\Entity\EditorDropdown;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<EditorDropdown> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<EditorDropdown> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method EditorDropdown|null fetchOne(?int $offset = null)
 * @extends Finder<EditorDropdown>
 */
class EditorDropdownFinder extends Finder
{
}
