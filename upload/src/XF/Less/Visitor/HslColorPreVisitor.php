<?php

namespace XF\Less\Visitor;

use Less_Tree as Tree;
use Less_Tree_Color as Color;
use Less_Tree_Ruleset as Ruleset;
use Less_VisitorReplacing as VisitorReplacing;
use XF\Less\Tree\HslColor;

class HslColorPreVisitor extends VisitorReplacing
{
	/**
	 * @var bool
	 */
	public $isPreVisitor = true;

	/**
	 * @var bool
	 */
	protected $enabled = true;

	public function setEnabled(bool $enabled): void
	{
		$this->enabled = $enabled;
	}

	public function getEnabled(): bool
	{
		return $this->enabled;
	}

	public function run(Ruleset $root): Tree
	{
		if (!$this->enabled)
		{
			return $root;
		}

		return $this->visitObj($root);
	}

	public function visitColor(Color $color): HslColor
	{
		return HslColor::fromColor($color);
	}
}
