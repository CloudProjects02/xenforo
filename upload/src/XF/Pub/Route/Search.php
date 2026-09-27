<?php

namespace XF\Pub\Route;

use XF\Mvc\RouteBuiltLink;
use XF\Mvc\Router;

class Search
{
	/**
	 * @return RouteBuiltLink|null
	 */
	public static function build(
		string &$prefix,
		array &$route,
		string &$action,
		&$data,
		array &$params,
		Router $router
	)
	{
		if (!($data instanceof \XF\Entity\Search))
		{
			return null;
		}

		$type = $data->search_type;
		if ($type !== '')
		{
			$params['t'] = $type;
		}

		$query = $data->search_query;
		if ($query !== '')
		{
			$params['q'] = $query;
		}

		$constraints = $data->search_constraints;
		if ($constraints !== [])
		{
			$params['c'] = $constraints;
		}

		$order = $data->search_order;
		if ($order !== '')
		{
			$params['o'] = $order;
		}

		$grouping = $data->search_grouping;
		if ($grouping)
		{
			$params['g'] = 1;
		}

		return null;
	}
}
