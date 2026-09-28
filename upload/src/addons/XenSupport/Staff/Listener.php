<?php

namespace XenSupport\Staff;

/**
 * Shared XenSupport footer credit injector (dedup-aware).
 *
 * Identical across every XenSupport addon: they all subscribe to
 * templater_template_post_render and try to inject the credit once. A marker
 * class on the injected HTML lets sibling addons detect "already done" and skip,
 * so even with several XenSupport addons installed the footer shows exactly one
 * credit line. If the injecting addon is removed, the next sibling takes over on
 * the following render. Fail-safe by design, no cross-addon dependency.
 */
class Listener
{
	const CREDIT_MARKER = 'xs-credit-injected';

	public static function injectXsCredit(
		\XF\Template\Templater $templater,
		$type,
		$template,
		&$output
	): void
	{
		if ($type !== 'public' || $template !== 'PAGE_CONTAINER') return;
		if (!is_string($output) || $output === '') return;
		if (strpos($output, self::CREDIT_MARKER) !== false) return;

		$credit = ' &middot; <a href="https://xen-support.com" target="_blank" rel="nofollow noopener" class="' . self::CREDIT_MARKER . '">XenForo add-ons by &copy;XenSupport</a>';

		$patterns = [
			'#(<p class="p-footer-copyright"[^>]*>[\s\S]*?)(</p>)#',
			'#(<div class="p-footer-copyright"[^>]*>[\s\S]*?)(</div>)#',
		];
		foreach ($patterns AS $pat)
		{
			$count = 0;
			$new = @preg_replace($pat, '$1' . $credit . '$2', $output, 1, $count);
			if ($new !== null && $count > 0)
			{
				$output = $new;
				return;
			}
		}
	}
}
