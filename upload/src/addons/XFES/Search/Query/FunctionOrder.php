<?php

namespace XFES\Search\Query;

class FunctionOrder
{
	/**
	 * @var array[]|\Closure[]
	 */
	protected $functions = [];

	/**
	 * @var bool
	 */
	protected $includeDefaultWeighting = true;

	/**
	 * @param array|\Closure|null $function
	 * @param bool|null           $includeDefaultWeighting
	 */
	public function __construct(
		$function = null,
		$includeDefaultWeighting = null
	)
	{
		if ($function !== null)
		{
			$this->addFunction($function);
		}

		if ($includeDefaultWeighting !== null)
		{
			$this->includeDefaultWeighting($includeDefaultWeighting);
		}
	}

	/**
	 * @param array|\Closure $function
	 *
	 * @return static
	 */
	public function addFunction($function)
	{
		$this->functions[] = $function;

		return $this;
	}

	/**
	 * @return array[]|\Closure[]
	 */
	public function getFunctions()
	{
		return $this->functions;
	}

	/**
	 * @param bool $include
	 *
	 * @return static
	 */
	public function includeDefaultWeighting($include)
	{
		$this->includeDefaultWeighting = $include;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getIncludeDefaultWeighting()
	{
		return $this->includeDefaultWeighting;
	}
}
