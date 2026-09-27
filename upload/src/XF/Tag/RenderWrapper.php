<?php

namespace XF\Tag;

use XF\Mvc\Entity\Entity;
use XF\PreEscapedInterface;

/**
 * @template T of Entity
 */
class RenderWrapper implements PreEscapedInterface
{
	/**
	 * @var AbstractHandler<T>
	 */
	protected $handler;

	/**
	 * @var T
	 */
	protected $result;

	/**
	 * @var array<mixed>
	 */
	protected $options;

	/**
	 * @param AbstractHandler<T> $handler
	 * @param T $result
	 * @param array<mixed> $options
	 */
	public function __construct(AbstractHandler $handler, Entity $result, array $options = [])
	{
		$this->handler = $handler;
		$this->result = $result;
		$this->options = $options;
	}

	/**
	 * @param array<string> $extraOptions
	 *
	 * @return string
	 */
	public function render(array $extraOptions = [])
	{
		return $this->handler->renderResult($this->result, array_merge($this->options, $extraOptions));
	}

	public function getPreEscapeType()
	{
		return 'html';
	}

	public function __toString()
	{
		try
		{
			return $this->render();
		}
		catch (\Exception $e)
		{
			\XF::logException($e, false, "Search render error: ");
			return '';
		}
	}

	/**
	 * @return AbstractHandler<T>
	 */
	public function getHandler()
	{
		return $this->handler;
	}

	/**
	 * @return T
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * @return array<mixed>
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * @param array<mixed> $options
	 *
	 * @return void
	 */
	public function mergeOptions(array $options)
	{
		$this->options = array_merge($this->options, $options);
	}
}
