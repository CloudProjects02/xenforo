<?php

namespace XF\ModeratorLog;

use XF\Entity\ModeratorLog;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

class Logger
{
	/**
	 * @var array<string, class-string<AbstractHandler>>
	 */
	protected $types;

	/**
	 * @var array<string, AbstractHandler>
	 */
	protected $handlers = [];

	/**
	 * @param array<string, class-string<AbstractHandler>> $types
	 */
	public function __construct(array $types)
	{
		$this->types = $types;
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return array<string, ModeratorLog>
	 */
	public function logChanges($type, Entity $content, $throw = true, ?User $actor = null)
	{
		$handler = $this->handler($type, $throw);
		if (!$handler)
		{
			return [];
		}

		$actor = $actor ?: \XF::visitor();
		if (!$handler->isLoggableUser($actor))
		{
			return [];
		}

		$logs = [];

		$potentials = [];

		$changedValues = $content->getNewValues();
		if ($changedValues)
		{
			foreach ($changedValues AS $field => $value)
			{
				$oldValue = $content->getExistingValue($field);
				if ($value !== $oldValue)
				{
					$potentials[$field] = ['new' => $value, 'old' => $oldValue];
				}
			}
		}
		else
		{
			foreach ($content->getPreviousValues() AS $field => $oldValue)
			{
				$value = $content->getValue($field);
				if ($value !== $oldValue)
				{
					$potentials[$field] = ['new' => $value, 'old' => $oldValue];
				}
			}
		}

		foreach ($potentials AS $field => $values)
		{
			$success = $handler->logChange($content, $field, $values['new'], $values['old'], $actor);
			if ($success)
			{
				$logs[$field] = $success;
			}
		}

		return $logs;
	}

	/**
	 * @param string $type
	 * @param Entity|int $content
	 * @param string $field
	 * @param bool $throw
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 *
	 * @return ModeratorLog|array{}|null
	 */
	public function logChange($type, $content, $field, $throw = true, $newValue = null, $oldValue = null, ?User $actor = null)
	{
		$handler = $this->handler($type, $throw);
		if (!$handler)
		{
			return [];
		}

		if (!($content instanceof Entity))
		{
			$content = $handler->getContent($content);
		}
		if (!($content instanceof Entity))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Content must be an entity or an ID of a valid entity");
			}
			else
			{
				return [];
			}
		}

		$actor = $actor ?: \XF::visitor();
		if (!$handler->isLoggableUser($actor))
		{
			return [];
		}

		if ($newValue === null)
		{
			$newValue = $content->getValue($field);
		}
		if ($oldValue === null)
		{
			$oldValue = $content->getExistingValue($field);
		}

		if ($newValue === $oldValue)
		{
			return null;
		}

		return $handler->logChange($content, $field, $newValue, $oldValue, $actor);
	}

	/**
	 * @param string $type
	 * @param Entity|int $content
	 * @param string $action
	 * @param array<mixed> $params
	 * @param bool $throw
	 *
	 * @return ModeratorLog|array{}|null
	 */
	public function log($type, $content, $action, array $params = [], $throw = true, ?User $actor = null)
	{
		$handler = $this->handler($type, $throw);
		if (!$handler)
		{
			return [];
		}

		if (!($content instanceof Entity))
		{
			$content = $handler->getContent($content);
		}
		if (!($content instanceof Entity))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Content must be an entity or an ID of a valid entity");
			}
			else
			{
				return [];
			}
		}

		$actor = $actor ?: \XF::visitor();
		if (!$handler->isLoggableUser($actor))
		{
			return [];
		}

		return $handler->log($content, $action, $params, $actor);
	}

	/**
	 * @return string
	 */
	public function getContentTitle(ModeratorLog $log)
	{
		return $this->handler($log->content_type)->getContentTitle($log);
	}

	/**
	 * @return string|\Stringable
	 */
	public function getAction(ModeratorLog $log)
	{
		return $this->handler($log->content_type)->getAction($log);
	}

	/**
	 * @param string $type
	 *
	 * @return bool
	 */
	public function isValidContentType($type)
	{
		return isset($this->types[$type]) && class_exists($this->types[$type]);
	}

	/**
	 * @param string $type
	 * @param bool $throw
	 *
	 * @return ($throw is true ? AbstractHandler : AbstractHandler|null)
	 */
	public function handler($type, $throw = true)
	{
		if (isset($this->handlers[$type]))
		{
			return $this->handlers[$type];
		}

		if (!isset($this->types[$type]))
		{
			if ($throw)
			{
				throw new \InvalidArgumentException("Unknown moderator handler type '$type'");
			}
			else
			{
				return null;
			}
		}

		$class = $this->types[$type];
		if (class_exists($class))
		{
			$class = \XF::extendClass($class);
		}

		$this->handlers[$type] = new $class($type);
		return $this->handlers[$type];
	}
}
