<?php

namespace XF\Service\Development;

use function count, is_string, strlen;

trait TemplateContentPhraserTrait
{
	protected $paramRegex = '(\{\{([\s\S]+?)\}\})|(\{\$[^{}]+(\{\$[^}]+\}[^}]*)?\})';

	protected $phrasePrefix = '';
	protected $addOnId = null;

	public function setAddOnId(string $addOnId, string $phrasePrefix = ''): void
	{
		$this->addOnId = $addOnId;
		$this->phrasePrefix = $phrasePrefix;
	}

	public function templateHasPhrasableText(string $content): bool
	{
		$hasPhrasable = false;

		$this->processTemplateReplacements($content, function ($match) use (&$hasPhrasable)
		{
			$replaceTarget = !empty($match['text_replace']) ? $match['text_replace'] : null;
			if ($this->substringIsPhrasable($match['text'], $replaceTarget))
			{
				$hasPhrasable = true;
			}
		});

		return $hasPhrasable;
	}

	public function phraseTemplate(string $content, array &$replaceData = []): string
	{
		$replaceData = [];

		$content = $this->processTemplateReplacements($content, function ($match) use (&$replaceData)
		{
			$replaceTarget = !empty($match['text_replace']) ? $match['text_replace'] : null;
			if (!$this->substringIsPhrasable($match['text'], $replaceTarget))
			{
				return $match[0];
			}

			$info = $this->processPhrasableText(
				$match[0],
				$match['text'],
				$replaceTarget
			);

			if ($info)
			{
				$replaceData[] = $info;

				return $info['replaced'];
			}
			else
			{
				return $match[0];
			}
		});

		return $content;
	}

	protected function processPhrasableText(string $fullMatch, string $text, ?string $replaceTarget = null): ?array
	{
		if (!$this->addOnId)
		{
			throw new \LogicException("Must have an add-on specified before running");
		}

		$originalText = $text;

		if (!is_string($replaceTarget) || substr($replaceTarget, 0, 2) != '~~')
		{
			// ~~ denotes an explicit opt-in to what's being phrased, accept that
			$text = $this->adjustPhrasableText($text);
		}

		if (!$text)
		{
			return null;
		}

		if ($replaceTarget === null)
		{
			// if we haven't explicitly denoted the replacement area, only replace what we think we can phrase
			$replaceTarget = $text;
		}

		$phraseText = $text;
		$parameters = $this->extractParametersFromText($phraseText);
		$phraseText = $this->replaceParametersWithPlaceholders($phraseText, $parameters);

		/** @var PhraserService $phraser */
		$phraser = $this->service(PhraserService::class);

		$phraseName = $phraser->getPhraseNameFromText($phraseText, $this->phrasePrefix);
		$info = $phraser->adjustForExistingPhrase($phraseName, $phraseText, $parameters, $this->addOnId);
		$phraseName = $info['phraseName'];
		$phraseText = $info['phraseText'];
		$parameters = $info['vars'];
		$exists = $info['exists'];

		$templateCode = $this->generatePhraseTemplateCode($phraseName, $parameters);

		return [
			'replaced' => str_replace($replaceTarget, $templateCode, $fullMatch),
			'replaceTarget' => $replaceTarget,
			'text' => $phraseText,
			'templateCode' => $templateCode,
			'phraseName' => $phraseName,
			'existingName' => $exists ? $phraseName : '',
			'originalText' => $originalText,
			'fullMatch' => $fullMatch,
		];
	}

	protected function processTemplateReplacements(string $text, callable $callback): string
	{
		$removedMap = [];
		$removedCount = 0;

		$text = preg_replace_callback(
			'#<(script|xf:js)[^>]*(?<!/)>.*</\\1>#siU',
			function ($match) use (&$removedMap, &$removedCount)
			{
				if (strpos($match[0], '~~') !== false)
				{
					// we have phraseable text here, don't replace it
					return $match[0];
				}

				$replace = '<xf:invalidsection placeholder="' . $removedCount . '" />';

				$removedMap[$replace] = $match[0];
				$removedCount++;

				return $replace;
			},
			$text
		);

		$text = preg_replace_callback(
			'#(?P<text_replace>~~(?P<text>.+)~~)#Us',
			$callback,
			$text
		);
		$text = preg_replace_callback(
			'#(?<!javascript"|:comment|:js|:css|/css")>(?!=|\s*\$|\s*\d+\s*\?|\s*\d+\s*")(?P<text>[^<]+)(?<!>)<(?=\S)(?!/xf:hiddenval)#s',
			$callback,
			$text
		);
		$text = preg_replace_callback(
			'#(?<=\s)(alt|title|label|save|submit|explain|snippet|hint|placeholder|aria-label|tooltip|arg-label|arg-title)="(?P<text>[^"]+)"#',
			$callback,
			$text
		);
		$text = preg_replace_callback(
			'#<input type="(submit|button|reset)"[^>]*value="(?P<text>[^"]+)"#',
			$callback,
			$text
		);

		foreach ($removedMap AS $find => $replace)
		{
			$text = str_replace($find, $replace, $text);
		}

		return $text;
	}

	protected function substringIsPhrasable(string $text, ?string $textReplace = null): bool
	{
		if ($textReplace && substr($textReplace, 0, 2) == '~~')
		{
			return true;
		}

		// has no letters
		if (!preg_match('#[a-z]#i', $text))
		{
			return false;
		}

		// something untext like at the beginning
		// note that ~~ is here because it should be matched where the contents are passed in
		if (preg_match('/^(~~|#|\$|http:|https:|DEBUG:|\\\\|php cmd\.php)/', ltrim($text)))
		{
			return false;
		}

		// This is probably matching > within an expression as a false positive. Normally this is a numeric
		// comparison in a ternary operator, so look for the number and the ternary and assume it's not text.
		if (preg_match('/^\s*\d+\s*(\?|\?:|AND|&&|OR|\|\|)/', $text))
		{
			return false;
		}

		// This is probably matching > within an expression as a false positive. This could involve a function call,
		// usually to count, on a variable so skip that.
		if (preg_match('/^\s*count\(\$/', $text))
		{
			return false;
		}

		if (preg_match('/{\$.*}\s+-&(?:gt|lt);\s+{\$.*}/', $text))
		{
			return false;
		}
		if ($this->isLikelyTemplateCodeFragment($text))
		{
			return false;
		}

		switch ($text)
		{
			case 'XenForo':
			case 'XenForo Ltd.':
			case 'px':
			case '_xfClientLoadTime':

			// while these could be real words, this case likely means that they have a programmatic meaning
			case 'option':
			case 'true':
			case 'false':
				return false;
		}

		switch ($text)
		{
			case 'N/A':
				return true;
		}

		$text = $this->adjustPhrasableText($text);

		if (preg_match('/^\'\s*\..*\.\s*\'$/', $text))
		{
			// looks like ' . something() . ' which is probably in the middle of an expression
			return false;
		}
		if (preg_match('/^\',\s*\'/', $text))
		{
			// looks like ', ' which is probably in the middle of an expression
			return false;
		}

		// too short
		if (strlen($text) < 2)
		{
			return false;
		}

		// special cases to skip
		if ($text == '%s' || $text == 'XenForo' || $text == 'Aa')
		{
			return false;
		}

		// just an entity
		if (preg_match('/^&[#a-z0-9]+;$/i', $text))
		{
			return false;
		}

		// all uppercase/numbers, likely an acronym
		if (preg_match('/^[A-Z0-9_\-]+$/', $text))
		{
			return false;
		}

		// likely a BB code
		if (preg_match('/^\[[A-Z]+(=|\])/', $text))
		{
			return false;
		}

		// likely jQuery call
		if (preg_match('#(\$|jQuery)(\.[a-zA-Z0-9_]+)?\(#', $text))
		{
			return false;
		}

		// likely an ld+json block
		if (strpos($text, '"https://schema.org"') !== false)
		{
			return false;
		}

		// just a param
		if (preg_match('#^(' . $this->paramRegex . '\s*([%|\-,]\s*)?)+$#', $text))
		{
			return false;
		}

		$replaced = preg_replace('#' . $this->paramRegex . '#', '', $text);
		$replaced = preg_replace('#\{[^}]+}#', '', $replaced);
		$replaced = trim($replaced);
		if (!preg_match('#[a-zA-Z]{2}#', $replaced))
		{
			return false; // basically all params
		}
		if (preg_match('/^[a-z0-9]+(_+[a-z0-9]*)+$/', $replaced))
		{
			return false; // x_y_z format, probably a set call to an internal thing
		}
		if (preg_match('#^[a-z0-9/_]+$#', $replaced))
		{
			return false; // all lower case with / and _ -- probably an internal value
		}
		if (preg_match('/^[a-zA-Z0-9_]+\[/', $replaced))
		{
			// probably a form input name
			return false;
		}
		if (preg_match('/^&[#a-z0-9]+;$/i', $replaced))
		{
			// just an entity
			return false;
		}
		if ($this->isLikelyUnitDimensionPattern($replaced))
		{
			return false;
		}
		if ($this->hasTemplateMarkerImbalance($text))
		{
			return false;
		}
		if ($this->isExpressionHeavyFragment($text))
		{
			return false;
		}
		if ($this->isLikelyTechnicalToken($text) || $this->isLikelyTechnicalToken($replaced))
		{
			return false;
		}

		return true;
	}

	protected function hasTemplateMarkerImbalance(string $text): bool
	{
		return (substr_count($text, '{{') !== substr_count($text, '}}'));
	}

	protected function isExpressionHeavyFragment(string $text): bool
	{
		if (!preg_match('/\{\{|\}\}|\{\$/', $text))
		{
			return false;
		}

		$stripped = preg_replace('/\{\{[\s\S]*?\}\}|\{\$[^}]+\}/', '', $text);
		if (!is_string($stripped))
		{
			return false;
		}

		$letterCount = preg_match_all('/[a-z]/i', $stripped);
		return ($letterCount !== false && $letterCount < 2);
	}

	protected function isLikelyTechnicalToken(string $text): bool
	{
		$text = trim($text);
		if ($text === '' || strpos($text, ' ') !== false)
		{
			return false;
		}

		$text = strtolower($text);
		if (!preg_match('/^[a-z0-9_\/-]+$/', $text))
		{
			return false;
		}

		if (preg_match('/^aria-[a-z0-9-]+$/', $text))
		{
			return true;
		}

		return (bool) preg_match('/^[a-z0-9]+(?:[-_\/][a-z0-9]+)+$/', $text);
	}

	protected function isLikelyTemplateCodeFragment(string $text): bool
	{
		if (preg_match('/[a-z0-9_-]+\s*=\s*(?:\"|\'|\{\{|\{\$)/i', $text))
		{
			return true;
		}
		if (preg_match('/\{\{\s*phrase\(/i', $text))
		{
			return true;
		}
		if (preg_match('/[a-z0-9_-]-\{\$[a-z0-9_.]+\}/i', $text))
		{
			return true;
		}
		if (preg_match('/#[a-z0-9_-]*\{\$[a-z0-9_.]+\}/i', $text))
		{
			return true;
		}

		return false;
	}

	protected function isLikelyUnitDimensionPattern(string $text): bool
	{
		$text = trim(strtolower($text));
		if ($text === '')
		{
			return false;
		}

		return (bool) preg_match(
			'/^(?:\d+(?:\.\d+)?\s*)?(?:px|em|rem|vh|vw|vmin|vmax|pt|pc|cm|mm|in|ch|ex|%)\s*x\s*(?:\d+(?:\.\d+)?\s*)?(?:px|em|rem|vh|vw|vmin|vmax|pt|pc|cm|mm|in|ch|ex|%)$/',
			$text
		);
	}

	protected function adjustPhrasableText(string $text): string
	{
		$text = trim($text);
		$text = ltrim(preg_replace('/^(&nbsp;\s*)+/', '', $text));
		$text = ltrim(preg_replace('/(\s*&nbsp;)+$/', '', $text));
		$text = trim(preg_replace('/^\((.*)\)$/', '\\1', $text));
		$text = ltrim(preg_replace('/^(' . $this->paramRegex . ') - /', '', $text));
		$text = rtrim(preg_replace('/((:|-|\\|)\s+)(\'|"|)((' . $this->paramRegex . ')\s*)+\\3$/', '', $text));
		$text = rtrim(preg_replace('/(\.)\s+(' . $this->paramRegex . ')$/', '\\1', $text));
		$text = ltrim(preg_replace('/^(' . $this->paramRegex . ')\s*(:|-|&middot;\s*|\\||)/i', '', $text));
		$text = ltrim(preg_replace('/^\{\{\s*phrase\((\'|")[a-z0-9_.]+:(\'|")\)\s*\}\}/', '', $text));
		$text = preg_replace_callback('/^(.*)\{\{(.*)$/siU', function ($match)
		{
			if (preg_match('/\}\}/', $match[2]))
			{
				// if we match a close, keep it
				return $match[0];
			}
			else
			{
				// if we don't, only phrase what comes before
				return rtrim($match[1]);
			}
		}, $text);
		$text = preg_replace_callback('/^(.*)\}\}(.*)$/siU', function ($match)
		{
			if (preg_match('/\{\{/', $match[1]))
			{
				// if we match an open, keep it
				return $match[0];
			}
			else
			{
				// if we don't, only phrase what comes after
				return ltrim($match[2]);
			}
		}, $text);
		$text = ltrim(preg_replace('/^(#|@)/', '', $text));
		$text = ltrim(preg_replace('/^(\+|,|\*|:|&[#a-z0-9]+;)\s+/', '', $text));
		$text = rtrim(preg_replace('/(\.\.\.|\:|\(|,|&[#a-z0-9]+;)$/', '', $text));
		$text = trim($text);

		return $text;
	}

	protected function extractParametersFromText(string $text): array
	{
		$parameters = [];

		preg_match_all('#' . $this->paramRegex . '#', $text, $matches, PREG_SET_ORDER);
		foreach ($matches AS $match)
		{
			if (preg_match('/\$([a-z0-9_]+)((\.[a-z0-9_]+)*)/i', $match[0], $nameMatch))
			{
				if (!empty($nameMatch[2]))
				{
					$parts = explode('.', $nameMatch[2]);
					$name = end($parts);
				}
				else
				{
					$name = $nameMatch[1];
				}

				$name = $this->mapParameterName($name);

				if (isset($parameters[$name]) and $parameters[$name] != $match[0])
				{
					$i = 1;
					while (isset($parameters[$name . $i]))
					{
						$i++;
					}
					$name = $name . $i;
				}
			}
			else
			{
				$name = 'param' . (count($parameters) + 1);
			}

			$parameters[$name] = $match[0];
		}

		return $parameters;
	}

	protected function mapParameterName(string $name): string
	{
		if (strtolower(substr($name, -5)) == 'count')
		{
			return 'count';
		}

		switch ($name)
		{
			case 'username': return 'name';
			case 'boardTitle': return 'board_title';
			default: return $name;
		}
	}

	public function replaceParametersWithPlaceholders(string $text, array $parameters): string
	{
		foreach ($parameters AS $name => $param)
		{
			$text = str_replace($param, '{' . $name . '}', $text);
		}

		return $text;
	}

	public function generatePhraseTemplateCode(string $name, array $parameters): string
	{
		$code = "{{ phrase('{$name}'";

		if ($parameters)
		{
			$code .= ', {';
			$first = true;

			foreach ($parameters AS $paramName => $param)
			{
				if (!$first)
				{
					$code .= ', ';
				}

				if (substr($param, 0, 2) == '{{' && substr($param, -2) == '}}')
				{
					$param = trim(substr($param, 2, -2));
				}

				if (preg_match('#^\{(\$[^{}]+)\}$#', $param, $varMatch))
				{
					$param = $varMatch[1];
				}

				$code .= "'{$paramName}': {$param}";
				$first = false;
			}

			$code .= '}';
		}

		$code .= ') }}';

		return $code;
	}
}
