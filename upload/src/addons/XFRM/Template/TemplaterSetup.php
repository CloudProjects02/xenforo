<?php

namespace XFRM\Template;

use XF\Template\Templater;
use XFRM\Entity\ResourceItem;

class TemplaterSetup
{
	public function fnResourceIcon($templater, &$escape, ResourceItem $resource, $size = 'm', $href = '', $attributes = [])
	{
		$escape = false;

		$size = preg_replace('#[^a-zA-Z0-9_-]#s', '', $size);

		if ($href)
		{
			$tag = 'a';
			$hrefAttr = 'href="' . htmlspecialchars($href) . '"';
		}
		else
		{
			$tag = 'span';
			$hrefAttr = '';
		}

		/** @var Templater $templater */
		$attributesString = $templater->getAttributesAsString($attributes);

		if (!$resource->icon_date)
		{
			return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size} avatar--resourceIconDefault\"><span></span><span class=\"u-srOnly\">" . \XF::phrase('xfrm_resource_icon') . "</span></{$tag}>";
		}
		else
		{
			$src = $resource->getIconUrl($size);

			return "<{$tag} {$hrefAttr} class=\"avatar avatar--{$size}\"{$attributesString}>"
				. '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($resource->title) . '" loading="lazy" />'
				. "</{$tag}>";
		}
	}
}
