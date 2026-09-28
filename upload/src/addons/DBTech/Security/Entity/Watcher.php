<?php

namespace DBTech\Security\Entity;

use DBTech\Security\Finder\WatcherFinder;
use DBTech\Security\Repository\WatcherRepository;
use DBTech\Security\Watcher\AbstractHandler;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int|null $watcher_id
 * @property string $watcher_type
 * @property bool $active
 * @property int $priority
 * @property array $rule_data
 * @property array $actions
 * @property array $extra_data
 *
 * GETTERS
 * @property-read Phrase $title
 * @property-read Phrase $description
 * @property-read string|Phrase $hint
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Security\Entity\WatcherLog> $LogEntries
 */
class Watcher extends Entity implements ViewableInterface
{
	/**
	 * @return bool
	 */
	public function canView(): bool
	{
		return $this->isActive();
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @return Phrase
	 * @throws \Exception
	 */
	public function getTitle(): Phrase
	{
		return $this->getWatcherHandler()->getTitle();
	}

	/**
	 * @return Phrase
	 * @throws \Exception
	 */
	public function getDescription(): Phrase
	{
		return $this->getWatcherHandler()->getDescription();
	}

	/**
	 * @return string|Phrase
	 * @throws \Exception
	 */
	public function getHint(): Phrase|string
	{
		if (!empty($this->rule_data['field']))
		{
			return \XF::phrase('dbtech_security_watcher_field.' . $this->rule_data['field']);
		}

		return '';
	}

	/**
	 * @return Phrase
	 * @throws \Exception
	 */
	public function getParsedRule(): Phrase
	{
		return $this->getWatcherHandler()->getParsedRule($this);
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getParsedActionSet(): array
	{
		$actions = [];

		if ($this->actions['closeForum'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.closeForum');
		}

		if ($this->actions['emailWebmaster'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.emailWebmaster');
		}

		if ($this->actions['banIp'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.banIp');
		}

		if ($this->actions['banUser'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.banUser');
		}

		if ($this->actions['emailUser'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.emailUser');
		}

		if ($this->actions['lockChange'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.lockChange');
		}

		if ($this->actions['lockReset'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.lockReset');
		}

		if ($this->actions['lockUser'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.lockUser');
		}

		if ($this->actions['adminLockUser'])
		{
			$actions[] = \XF::phrase('dbtech_security_actions.adminLockUser');
		}

		return $actions;
	}

	/**
	 * @param bool $throw
	 *
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getWatcherHandler(bool $throw = true): ?AbstractHandler
	{
		return \XF::app()->repository(WatcherRepository::class)
			->getHandler($this->watcher_type, $throw)
		;
	}

	/**
	 * @return bool
	 */
	protected function _preSave(): bool
	{
		$exists = \XF::app()->finder(WatcherFinder::class)
			->where('watcher_type', $this->watcher_type)
			->where('rule_data', $this->getValueSourceEncoded('rule_data'))
			->fetchOne();
		if ($exists && $exists !== $this)
		{
			$this->error(\XF::phrase('dbtech_security_specified_watcher_rule_set_already_exists'));
			return false;
		}

		return true;
	}

	/**
	 *
	 */
	protected function _postDelete(): void
	{
		$db = $this->db();
		$db->delete('xf_dbtech_security_watcher_log', 'watcher_id = ?', $this->watcher_id);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_security_watcher';
		$structure->shortName = 'DBTech\Security:Watcher';
		$structure->primaryKey = 'watcher_id';
		$structure->columns = [
			'watcher_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'watcher_type' => ['type' => self::STR, 'required' => true],
			'active' => ['type' => self::BOOL, 'default' => true],
			'priority' => ['type' => self::UINT, 'default' => 1],
			'rule_data' => ['type' => self::JSON_ARRAY, 'default' => []],
			'actions' => ['type' => self::JSON_ARRAY, 'default' => []],
			'extra_data' => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'title' => true,
			'description' => true,
			'hint' => true,
			'explain' => true,
		];
		$structure->behaviors = [
			'DBTech\Security:Cacheable' => [],
		];
		$structure->relations = [
			'LogEntries' => [
				'entity' => WatcherLog::class,
				'type' => self::TO_MANY,
				'conditions' => 'watcher_id',
			],
		];

		return $structure;
	}
}