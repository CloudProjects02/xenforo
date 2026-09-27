<?php

namespace XF\Service\Development;

use XF\Entity\Phrase;
use XF\Service\AbstractService;

use function count;

class PhraserService extends AbstractService
{
	public function getPhraseNameFromText(string $name, string $prefix = ''): string
	{
		// initial clean up - note that {} is allowed for placeholders
		$name = strip_tags($name);
		$name = preg_replace('/^(.+)(\.|\?|!).*$/U', '\\1', $name); // only consider the first sentence
		$name = strtr($name, [
			'+' => ' plus ',
			'could not be found' => 'not found',
		]);
		$name = preg_replace('/[^a-z0-9{}_\/\s\-]/i', '', strtolower($name));

		// limit the length and make it more phrase like
		$name = $this->app->stringFormatter()->wholeWordTrim($name, 75, 0, '');
		$name = str_replace([' ', "\r", "\n", "\t", '-', '/'], '_', $name);

		// clean up unimportant parts and remove unnecessary underscores
		$name = preg_replace('/(^|_)(a|an|the|are)(?=_|$)/', '', $name);

		// replace placeholders with a simple version
		$placeholders = ['x', 'y', 'z', 'a', 'b', 'c'];
		$placeholderCount = count($placeholders);
		preg_match_all('/\{([^}]+)\}/', $name, $placeholderMatches, PREG_SET_ORDER);
		foreach ($placeholderMatches AS $placeholderKey => $match)
		{
			$replacement = $placeholders[$placeholderKey] ?? $placeholders[$placeholderKey % $placeholderCount];
			$name = str_replace($match[0], $replacement, $name);
		}

		$name = strtr($name, [
			'{' => '',
			'}' => '',
		]);

		$name = preg_replace('/^_+/', '', $name);
		$name = preg_replace('/_+$/', '', $name);
		$name = preg_replace('/_{2,}/', '_', $name);

		return $prefix . $name;
	}

	public function adjustForExistingPhrase(string $name, string $text, array $vars, string $limitAddOnId = 'XF'): array
	{
		$masterPhrase = $this->em()->findOne(Phrase::class, [
			'language_id' => 0,
			'title' => $name,
		]);

		if ($masterPhrase)
		{
			$exists = true;
			$existingMasterValue = $masterPhrase->phrase_text;

			// map the {vars} in the new phrase to those in the old phrase
			preg_match_all('/\{([^}]+)\}/', $text, $placeholderMatches, PREG_SET_ORDER);
			preg_match_all('/\{([^}]+)\}/', $existingMasterValue, $existingPlaceholderMatches, PREG_SET_ORDER);

			$oldVars = $vars;
			$vars = [];

			foreach ($placeholderMatches AS $placeholderKey => $match)
			{
				if (
					!isset($existingPlaceholderMatches[$placeholderKey])
					|| !isset(
						$existingPlaceholderMatches[$placeholderKey][0],
						$existingPlaceholderMatches[$placeholderKey][1],
						$match[1],
						$oldVars[$match[1]]
					)
				)
				{
					continue;
				}

				$text = str_replace($match[0], $existingPlaceholderMatches[$placeholderKey][0], $text);

				$vars[$existingPlaceholderMatches[$placeholderKey][1]] = $oldVars[$match[1]];
			}
		}
		else
		{
			$existingMasterName = $this->db()->fetchOne("
				SELECT title
				FROM xf_phrase
				WHERE language_id = 0
					AND phrase_text = ?
					AND title NOT LIKE '%.%'
					AND addon_id IN ('XF', ?)
			", [$text, $limitAddOnId]);
			if ($existingMasterName)
			{
				$name = $existingMasterName;
				$exists = true;
			}
			else
			{
				$exists = false;
			}
		}

		return [
			'phraseText' => $text,
			'phraseName' => $name,
			'vars' => $vars,
			'exists' => $exists,
		];
	}
}
