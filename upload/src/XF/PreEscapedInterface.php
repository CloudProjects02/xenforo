<?php

namespace XF;

interface PreEscapedInterface
{
	/**
	 * @return string
	 */
	public function getPreEscapeType();

	/**
	 * @return string
	 */
	public function __toString();
}
