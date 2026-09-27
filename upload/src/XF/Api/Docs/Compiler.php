<?php

namespace XF\Api\Docs;

use XF\Api\Docs\Annotation\RouteBlock;
use XF\Api\Docs\Renderer\RendererInterface;

class Compiler
{
	/**
	 * @var AnnotationParser
	 */
	protected $annotationParser;

	/**
	 * @var ClassParser
	 */
	protected $classParser;

	/**
	 * @var RouteBlock[][]
	 */
	protected $routesByAddOn = [];

	/**
	 * @var Annotation\TypeBlock[]
	 */
	protected $types = [];

	public static $methodSortOrder = [
		'GET' => 0,
		'POST' => 1,
		'PUT' => 2,
		'PATCH' => 3,
		'DELETE' => 4,
	];

	public function __construct(AnnotationParser $annotationParser, ClassParser $classParser)
	{
		$annotationParser->setClassParser($classParser);

		$this->annotationParser = $annotationParser;
		$this->classParser = $classParser;
	}

	public function compileForAddOn($addOnId)
	{
		$ds = \XF::$DS;

		// add-on IDs use a forward slash (not a backslash), so standardize
		$addOnId = str_replace('\\', '/', $addOnId);

		if ($addOnId == 'XF')
		{
			$baseDir = \XF::getSourceDirectory() . $ds . 'XF';
		}
		else
		{
			$baseDir = \XF::getAddOnDirectory() . $ds . str_replace('/', $ds, $addOnId);
		}

		$entityDir = "{$baseDir}{$ds}Entity";
		if (file_exists($entityDir))
		{
			$classPrefix = str_replace('/', '\\', $addOnId);

			foreach (new \DirectoryIterator($entityDir) AS /** @var \DirectoryIterator $file */ $file)
			{
				if ($file->getExtension() == 'php')
				{
					$entityName = $classPrefix . ':' . substr($file->getBasename(), 0, -4);
					$typeBlock = $this->classParser->parseEntityClass($entityName);
					if ($typeBlock)
					{
						$this->types[$typeBlock->type] = $typeBlock;
					}
				}
			}
		}

		$this->routesByAddOn[$addOnId] = [];

		$apiRoutes = \XF::db()->fetchAll("
			SELECT route_prefix, format, controller, action_prefix
			FROM xf_route
			WHERE route_type = 'api'
				AND addon_id = ?
		", $addOnId);
		foreach ($apiRoutes AS $route)
		{
			$controllerRoutes = $this->classParser->parseControllerClass(
				$route['controller'],
				$this->getRouteUrl($route['route_prefix'], $route['format']),
				$route['action_prefix'] ?? ''
			);
			$this->routesByAddOn[$addOnId] = array_merge($this->routesByAddOn[$addOnId], $controllerRoutes);
		}
	}

	protected function getRouteUrl($prefix, $format)
	{
		$extra = $format;

		$extra = preg_replace(
			'#:(\+)?int(?:_p)?<([a-zA-Z0-9_]+)(?:,[a-zA-Z0-9_]+)?>/?#',
			'{$2}/',
			$extra
		);

		$extra = preg_replace(
			'#:(\+)?str(?:_p)?<([a-zA-Z0-9_]+)>/?#',
			'{$2}/',
			$extra
		);

		$extra = preg_replace(
			'#:page<([a-zA-Z0-9_]+)>/?#',
			'page-{page}',
			$extra
		);
		$extra = preg_replace(
			'#:page/?#',
			'page-{page}',
			$extra
		);

		$extra = preg_replace(
			'#:(\+)?any<([a-zA-Z0-9_]+)>/?#',
			'{$2}/',
			$extra
		);

		// simplify names to "id" if they end in _id
		$extra = preg_replace(
			'#\{[a-zA-Z0-9_]+_id\}#',
			'{id}',
			$extra
		);

		return $prefix . '/' . $extra;
	}

	public function getRoutesFlattened()
	{
		$routes = [];
		foreach ($this->routesByAddOn AS $addOnRoutes)
		{
			foreach ($addOnRoutes AS $k => $v)
			{
				$routes[$k] = $v;
			}
		}

		return $this->sortRoutes($routes);
	}

	public function getRoutesByGroup()
	{
		$routeGroupings = [];

		foreach ($this->routesByAddOn AS $addOnRoutes)
		{
			foreach ($addOnRoutes AS $k => $route)
			{
				$group = $route->group ?: 'ungrouped';
				$routeGroupings[$group][$k] = $route;
			}
		}

		return $this->sortRoutesGrouped($routeGroupings);
	}

	public function getRoutesByAddOn()
	{
		return $this->routesByAddOn;
	}

	public function getRoutesForAddOn($addOnId)
	{
		return $this->routesByAddOn[$addOnId] ?? [];
	}

	public function addOnHasRoutes($addOnId)
	{
		return isset($this->routesByAddOn[$addOnId]);
	}

	/**
	 * @param Annotation\TypeBlock[] $types
	 *
	 * @return Annotation\TypeBlock[]
	 */
	public function getTypes()
	{
		return $this->sortTypes($this->types);
	}

	/**
	 * @param RouteBlock[] $routes
	 *
	 * @return RouteBlock[]
	 */
	public function sortRoutes(array $routes)
	{
		uasort($routes, function (RouteBlock $r1, RouteBlock $r2)
		{
			if ($r1->route !== $r2->route)
			{
				return ($r1->route < $r2->route ? -1 : 1);
			}

			$r1Order = self::$methodSortOrder[$r1->method] ?? 100;
			$r2Order = self::$methodSortOrder[$r2->method] ?? 100;
			return $r1Order <=> $r2Order;
		});

		return $routes;
	}

	/**
	 * @param RouteBlock[][] $routesGrouped
	 *
	 * @return RouteBlock[][]
	 */
	public function sortRoutesGrouped(array $routesGrouped)
	{
		ksort($routesGrouped);

		foreach ($routesGrouped AS &$routes)
		{
			$routes = $this->sortRoutes($routes);
		}

		return $routesGrouped;
	}

	/**
	 * @param Annotation\TypeBlock[] $types
	 *
	 * @return Annotation\TypeBlock[]
	 */
	public function sortTypes(array $types): array
	{
		ksort($types);
		return $types;
	}

	public function render(RendererInterface $renderer)
	{
		return $renderer->render($this->getRoutesByGroup(), $this->getTypes());
	}

	public function renderFiltered(RendererInterface $renderer, array $filters)
	{
		$filteredRoutes = $this->filterRoutes($filters);

		$filteredTypes = $this->filterTypes($filteredRoutes);

		return $renderer->render($this->groupRoutes($filteredRoutes), $filteredTypes);
	}

	public function filterRoutes(array $filters): array
	{
		$allRoutes = $this->getRoutesFlattened();
		$filteredRoutes = [];

		foreach ($filters AS $pair)
		{
			$pair = trim($pair);
			if ($pair === '')
			{
				continue;
			}

			[$method, $route] = array_pad(explode(' ', $pair, 2), 2, null);

			$method = $method ? strtoupper(trim($method)) : '';
			$route = $route ? trim($route) : '';

			if ($method === '' || $route === '')
			{
				continue;
			}

			foreach ($allRoutes AS $key => $routeBlock)
			{
				if ($routeBlock->method === $method && $routeBlock->route === $route)
				{
					$filteredRoutes[$key] = $routeBlock;
				}
			}
		}

		return $this->sortRoutes($filteredRoutes);
	}

	public function filterTypes(array $filteredRoutes): array
	{
		$allTypes = $this->getTypes();
		$referencedTypes = [];

		foreach ($filteredRoutes AS $route)
		{
			foreach ($route->inputs AS $input)
			{
				$referencedTypes = array_merge($referencedTypes, $input->types);
			}
			foreach ($route->outputs AS $output)
			{
				$referencedTypes = array_merge($referencedTypes, $output->types);
			}
		}

		$referencedTypes = $this->normalizeTypeNames($referencedTypes);

		$resolvedTypes = $this->resolveTypeDependencies($referencedTypes, $allTypes);

		return $resolvedTypes;
	}

	protected function normalizeTypeNames(array $types): array
	{
		$normalized = [];
		foreach ($types AS $type)
		{
			$normalized[] = rtrim($type, '[]');
		}
		return array_unique($normalized);
	}

	protected function resolveTypeDependencies(array $referencedTypes, array $allTypes): array
	{
		$resolved = [];
		$queue = $referencedTypes;

		while (!empty($queue))
		{
			$typeName = array_shift($queue);

			if (isset($resolved[$typeName]))
			{
				continue;
			}

			if (isset($allTypes[$typeName]))
			{
				$typeBlock = $allTypes[$typeName];
				$resolved[$typeName] = $typeBlock;

				foreach ($typeBlock->structure AS $field)
				{
					$queue = array_merge($queue, $this->normalizeTypeNames($field->types));
				}
			}
		}

		return $resolved;
	}

	protected function groupRoutes(array $routes): array
	{
		$routeGroupings = [];

		foreach ($routes AS $key => $route)
		{
			$group = $route->group ?: 'ungrouped';
			$routeGroupings[$group][$key] = $route;
		}

		return $this->sortRoutesGrouped($routeGroupings);
	}
}
