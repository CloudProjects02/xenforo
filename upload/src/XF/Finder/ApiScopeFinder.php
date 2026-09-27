<?php

namespace XF\Finder;

use XF\Entity\ApiScope;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ApiScope> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ApiScope> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ApiScope|null fetchOne(?int $offset = null)
 * @extends Finder<ApiScope>
 */
class ApiScopeFinder extends Finder
{
	public function usableForOAuth(bool $oAuthEnabled = true): Finder
	{
		return $this->where('usable_with_oauth_clients', $oAuthEnabled);
	}
}
