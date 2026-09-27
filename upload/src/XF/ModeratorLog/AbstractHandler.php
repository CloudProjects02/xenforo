<?php

namespace XF\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Util\Ip;

use function count, intval, is_array;

/**
 * @template T of Entity
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @param T $content
	 * @param string $field
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 *
	 * @return string|array{string, array<mixed>}|false
	 */
	abstract protected function getLogActionForChange(Entity $content, $field, $newValue, $oldValue);

	/**
	 * @param T $content
	 *
	 * @return void
	 */
	abstract protected function setupLogEntityContent(ModeratorLog $log, Entity $content);

	/**
	 * @param T $content
	 * @param string $action
	 *
	 * @return bool
	 */
	public function isLoggable(Entity $content, $action, User $actor)
	{
		return true;
	}

	/**
	 * @return bool
	 */
	public function isLoggableUser(User $actor)
	{
		return ($actor->user_id && $actor->is_moderator);
	}

	/**
	 * @param T $content
	 * @param string $field
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 *
	 * @return ModeratorLog|null
	 */
	public function logChange(Entity $content, $field, $newValue, $oldValue, User $actor)
	{
		$action = $this->getLogActionForChange($content, $field, $newValue, $oldValue);
		if (!$action)
		{
			return null;
		}

		if (is_array($action))
		{
			[$action, $params] = $action;
		}
		else
		{
			$params = [];
		}

		return $this->log($content, $action, $params, $actor);
	}

	/**
	 * @param T $content
	 * @param string $action
	 * @param array<mixed> $params
	 *
	 * @return ModeratorLog|null
	 */
	public function log(Entity $content, $action, array $params, User $actor)
	{
		if (!$this->isLoggable($content, $action, $actor))
		{
			return null;
		}

		$log = \XF::em()->create(ModeratorLog::class);
		$log->content_type = $this->contentType;
		$id = $content->getIdentifierValues();
		if (!$id || count($id) != 1)
		{
			throw new \InvalidArgumentException("Entity does not have an ID or does not have a simple key");
		}
		$log->content_id = intval(reset($id));

		$this->setupLogEntityActor($log, $actor);
		$this->setupLogEntityContent($log, $content);

		$log->action = $action;
		$log->action_params = $params;
		$log->save();

		return $log;
	}

	/**
	 * @return void
	 */
	protected function setupLogEntityActor(ModeratorLog $log, User $actor)
	{
		$log->user_id = $actor->user_id ?? 0;

		if ($actor->user_id == \XF::visitor()->user_id)
		{
			$ip = \XF::app()->request()->getIp();
			if ($ip !== '')
			{
				$log->ip_address = Ip::stringToBinary($ip, false) ?: '';
			}
		}
	}

	/**
	 * @return string
	 */
	public function getContentTitle(ModeratorLog $log)
	{
		return \XF::app()->stringFormatter()->censorText($log->content_title_);
	}

	/**
	 * @return string|\Stringable
	 */
	public function getAction(ModeratorLog $log)
	{
		return \XF::phrase(
			$this->getActionPhraseName($log),
			$this->getActionPhraseParams($log)
		);
	}

	/**
	 * @return string
	 */
	public function getActionPhraseName(ModeratorLog $log)
	{
		return 'mod_log.' . $log->content_type . '_' . $log->action;
	}

	/**
	 * @return array<string>
	 */
	protected function getActionPhraseParams(ModeratorLog $log)
	{
		$pather = \XF::app()['request.pather'];

		$params = $log->action_params;

		foreach ($params AS $key => $param)
		{
			if (preg_match('/url$/i', $key))
			{
				$params[$key] = $pather($param, 'base');
			}
		}

		return $params;
	}

	/**
	 * @return list<string>
	 */
	public function getEntityWith()
	{
		return [];
	}

	/**
	 * @param int|list<int> $id
	 *
	 * @return ($id is int ? T|null : AbstractCollection<T>)
	 */
	public function getContent($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getEntityWith());
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}
}
