<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Credits\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Credits\Entity\Event> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Credits\Entity\Event> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Credits\Entity\Event|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Credits\Entity\Event>
 */
class EventFinder extends Finder
{
	/**
	 * @param string $match
	 * @param bool $caseSensitive
	 * @param bool $prefixMatch
	 * @param bool $exactMatch
	 *
	 * @return $this
	 */
	public function searchText(
		string $match,
		bool $caseSensitive = false,
		bool $prefixMatch = false,
		bool $exactMatch = false
	): EventFinder
	{
		if ($match)
		{
			$expression = 'title';
			if ($caseSensitive)
			{
				$expression = $this->expression('BINARY %s', $expression);
			}

			if ($exactMatch)
			{
				$this->where($expression, $match);
			}
			else
			{
				$this->where($expression, 'LIKE', $this->escapeLike($match, $prefixMatch ? '?%' : '%?%'));
			}
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function orderForList(): EventFinder
	{
		$this->orderTitle();

		return $this;
	}

	/**
	 * @param string $direction
	 * @return $this
	 */
	public function orderTitle(string $direction = 'ASC'): EventFinder
	{
		$expression = $this->columnUtf8('title');
		$this->order($expression, $direction);

		return $this;
	}

	/**
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function useDefaultOrder(): EventFinder
	{
		$defaultOrder = 'title';
		/** @noinspection PhpConditionAlreadyCheckedInspection */
		$defaultDir = $defaultOrder == 'title' ? 'asc' : 'desc';

		$this->setDefaultOrder($defaultOrder, $defaultDir);

		return $this;
	}
}