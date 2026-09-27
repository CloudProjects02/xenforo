<?php

namespace XF\Template\Compiler\Syntax;

use XF\Template\Compiler;

class NullCoalesceOperator extends AbstractSyntax
{
	/**
	 * @var AbstractSyntax
	 */
	public $left;

	/**
	 * @var AbstractSyntax
	 */
	public $right;

	public function __construct(AbstractSyntax $left, AbstractSyntax $right, int $line)
	{
		$this->left = $left;
		$this->right = $right;
		$this->line = $line;
	}

	public function compile(Compiler $compiler, array $context, $inlineExpected): string
	{
		$left = $this->left->compile($compiler, $context, true);
		$right = $this->right->compile($compiler, $context, true);

		if (!$this->left->isSimpleValue())
		{
			$left = "($left)";
		}
		if (!$this->right->isSimpleValue())
		{
			$right = "($right)";
		}

		return "($left ?? $right)";
	}

	public function isSimpleValue(): bool
	{
		return true;
	}
}
