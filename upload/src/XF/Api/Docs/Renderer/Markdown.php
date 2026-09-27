<?php

namespace XF\Api\Docs\Renderer;

use XF\Api\Docs\Annotation\AbstractBlock;
use XF\Api\Docs\Annotation\AbstractValueLine;
use XF\Api\Docs\Annotation\RouteBlock;
use XF\Api\Docs\Annotation\TypeBlock;

class Markdown implements RendererInterface
{
	use FileRendererTrait;

	protected $types = [];

	public function renderInternal(array $routeGroupings, array $types): string
	{
		$this->types = $types;

		$md = [];

		$md[] = '# Table of Contents';
		$md[] = '';
		$md[] = $this->renderTableOfContents($routeGroupings, $types);
		$md[] = '';
		$md[] = '---';
		$md[] = '';

		$md[] = '# Routes';
		$md[] = '';

		foreach ($routeGroupings AS $group => $routes)
		{
			$md[] = '## ' . $this->escapeMarkdown($group);
			$md[] = '';

			foreach ($routes AS $route)
			{
				$md[] = $this->renderRoute($route);
				$md[] = '';
				$md[] = '---';
				$md[] = '';
			}
		}

		$md[] = '# Types';
		$md[] = '';

		foreach ($types AS $type)
		{
			$md[] = $this->renderType($type);
			$md[] = '';
		}

		$this->types = [];

		return implode("\n", $md);
	}

	public function renderTableOfContents(array $routeGroupings, array $types): string
	{
		$md = [];

		$md[] = '## Routes';

		foreach ($routeGroupings AS $group => $routes)
		{
			$md[] = '';
			$md[] = '### ' . $this->escapeMarkdown($group);
			$md[] = '';
			$md[] = '| Method | Path | Description |';
			$md[] = '| --- | --- | --- |';

			foreach ($routes AS $route)
			{
				$routeId = $this->getRouteId($route);
				$description = $route->description ? $this->escapeMarkdown($route->description) : '';
				$md[] = '| `' . $route->method . '` | [' . $this->escapeMarkdown($route->route) . '](#' . $routeId . ') | ' . $description . ' |';
			}
		}

		$md[] = '';
		$md[] = '## Types';
		$md[] = '';
		$md[] = '| Type | Description |';
		$md[] = '| --- | --- |';

		foreach ($types AS $type)
		{
			$typeId = $this->getTypeId($type);
			$description = $type->description ? $this->escapeMarkdown($type->description) : '';
			$md[] = '| [' . $this->escapeMarkdown($type->type) . '](#' . $typeId . ') | ' . $description . ' |';
		}

		return implode("\n", $md);
	}

	public function renderRoute(RouteBlock $route): string
	{
		$markdown = [];

		$routeId = $this->getRouteId($route);

		$markdown[] = '<a id="' . $routeId . '"></a>';
		$markdown[] = '';
		$markdown[] = '### ' . $route->method . ' ' . $this->escapeMarkdown($route->route);

		if ($route->description || $route->incomplete)
		{
			$markdown[] = '';
			$markdown[] = ($route->description ? $this->escapeMarkdown($route->description) : '')
				. ($route->incomplete ? ' *\[Incomplete\]*' : '');
		}

		$markdown[] = '';
		$markdown[] = '#### Inputs';
		$markdown[] = '';

		if ($route->inputs)
		{
			$markdown[] = '| Name | Type | Description |';
			$markdown[] = '| --- | --- | --- |';
			foreach ($route->inputs AS $input)
			{
				$markdown[] = $this->renderValueRow($input);
			}
		}
		else
		{
			$markdown[] = $this->renderEmptyValuesPlaceholder($route);
		}

		$markdown[] = '';
		$markdown[] = '#### Outputs';
		$markdown[] = '';

		if ($route->outputs)
		{
			$markdown[] = '| Name | Type | Description |';
			$markdown[] = '| --- | --- | --- |';
			foreach ($route->outputs AS $output)
			{
				$markdown[] = $this->renderValueRow($output);
			}
		}
		else
		{
			$markdown[] = $this->renderEmptyValuesPlaceholder($route);
		}

		if ($route->errors)
		{
			$markdown[] = '';
			$markdown[] = '#### Errors';
			$markdown[] = '';
			$markdown[] = '| Error | Description |';
			$markdown[] = '| --- | --- |';
			foreach ($route->errors AS $errorKey => $description)
			{
				$markdown[] = '| `' . $this->escapeMarkdown($errorKey) . '` | ' . $this->escapeMarkdown($description) . ' |';
			}
		}

		return implode("\n", $markdown);
	}

	protected function getRouteId(RouteBlock $route): string
	{
		$routeId = preg_replace('#[{}]#', '', $route->route);
		$routeId = preg_replace('#[/\-]#', '_', $routeId);
		$routeId = preg_replace('#[^a-z0-9_]#i', '', $routeId);

		return strtolower('route_' . $route->method . '_' . $routeId);
	}

	public function renderType(TypeBlock $type): string
	{
		$md = [];

		$typeId = $this->getTypeId($type);

		$md[] = '<a id="' . $typeId . '"></a>';
		$md[] = '';
		$md[] = '### ' . $this->escapeMarkdown($type->type);

		if ($type->description || $type->incomplete)
		{
			$md[] = '';
			$md[] = ($type->description ? $this->escapeMarkdown($type->description) : '')
				. ($type->incomplete ? ' *\[Incomplete\]*' : '');
		}

		$md[] = '';

		if ($type->structure)
		{
			$md[] = '| Column | Type | Description |';
			$md[] = '| --- | --- | --- |';
			foreach ($type->structure AS $element)
			{
				$md[] = $this->renderValueRow($element);
			}
		}
		else
		{
			$md[] = $this->renderEmptyValuesPlaceholder($type);
		}

		return implode("\n", $md);
	}

	protected function getTypeId(TypeBlock $type): string
	{
		return 'type_' . $type->type;
	}

	protected function renderValueRow(AbstractValueLine $value): string
	{
		$typeParts = [];
		foreach ($value->types AS $type)
		{
			if (preg_match('#^([a-z0-9_]+)(\[.*$)#i', $type, $match))
			{
				$typeSimple = $match[1];
				$typeExtended = $match[2];
			}
			else
			{
				$typeSimple = $type;
				$typeExtended = '';
			}

			if (isset($this->types[$typeSimple]))
			{
				$typeId = $this->getTypeId($this->types[$typeSimple]);
				$typeParts[] = '[`' . $typeSimple . '`](#' . $typeId . ')' . $this->escapeMarkdown($typeExtended);
			}
			else
			{
				$typeParts[] = '`' . $typeSimple . '`' . $this->escapeMarkdown($typeExtended);
			}
		}

		$description = '';
		if ($value->modifiers)
		{
			$description .= implode(' ', array_map(function ($m)
			{
				return '`' . $m . '`';
			}, $value->modifiers)) . ' ';
		}
		$description .= $this->escapeMarkdown($value->description);

		return '| `' . $this->escapeMarkdown($value->name) . '` | '
			. implode(', ', $typeParts) . ' | '
			. $description . ' |';
	}

	protected function renderEmptyValuesPlaceholder(AbstractBlock $block): string
	{
		if ($block->incomplete)
		{
			return '*Unknown, documentation incomplete.*';
		}

		return '*None.*';
	}

	protected function escapeMarkdown(string $text): string
	{
		return str_replace(
			['|', '[', ']', '`'],
			['\\|', '\\[', '\\]', '\\`'],
			$text
		);
	}
}
