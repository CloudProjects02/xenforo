<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Security\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Security\Entity\Country> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Security\Entity\Country> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Security\Entity\Country|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Security\Entity\Country>
 */
class CountryFinder extends Finder
{
	/**
	 * @param string $match
	 * @param bool $caseSensitive
	 * @param bool $prefixMatch
	 * @return $this
	 */
	public function searchText(
		string $match,
		bool $caseSensitive = false,
		bool $prefixMatch = false
	): CountryFinder
	{
		if ($match)
		{
			$expression = 'name';
			if ($caseSensitive)
			{
				$expression = $this->expression('BINARY %s', $expression);
			}

			$this->where($expression, 'LIKE', $this->escapeLike($match, $prefixMatch ? '?%' : '%?%'));
		}

		return $this;
	}

	/**
	 * @param string $direction
	 * @return $this
	 */
	public function orderName(string $direction = 'ASC'): CountryFinder
	{
		$expression = $this->columnUtf8('name');
		$this->order($expression, $direction);

		return $this;
	}
}