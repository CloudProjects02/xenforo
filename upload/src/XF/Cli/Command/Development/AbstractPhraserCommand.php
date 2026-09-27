<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Cli\Command\AbstractCommand;
use XF\Entity\AddOn;

use function array_slice, count, intval, strlen;

abstract class AbstractPhraserCommand extends AbstractCommand
{
	protected function getAddOnById(string $addOnId): ?AddOn
	{
		if (!$addOnId)
		{
			return null;
		}

		return \XF::em()->find(AddOn::class, $addOnId);
	}

	protected function getDefaultPhrasePrefix(string $addOnId): string
	{
		if ($addOnId === 'XF')
		{
			return '';
		}

		return strtolower(str_replace('/', '_', $addOnId)) . '_';
	}

	protected function normalizeTemplateReplacements(array $replaceData): array
	{
		$replacements = [];
		foreach ($replaceData AS $replace)
		{
			$replacements[] = [
				'original' => $replace['replaceTarget'],
				'name' => $replace['phraseName'],
				'text' => $replace['text'],
				'code' => $replace['templateCode'],
				'existingName' => $replace['existingName'],
			];
		}

		return $replacements;
	}

	protected function outputJson(OutputInterface $output, array $payload): int
	{
		$json = json_encode($payload, JSON_PRETTY_PRINT);
		if ($json === false)
		{
			$output->writeln('<error>Failed to encode JSON: ' . json_last_error_msg() . '</error>');
			return Command::FAILURE;
		}

		$output->writeln($json);
		return Command::SUCCESS;
	}

	protected function selectReplacements(SymfonyStyle $io, array $replacements): array
	{
		$count = count($replacements);
		$io->newLine();

		if ($count === 1)
		{
			$hint = '1 or all or none';
		}
		else
		{
			$hint = sprintf('1,%d or 1-%d or all or none', $count, $count);
		}

		$selection = $io->ask(
			sprintf('Select replacements to apply (e.g. %s)', $hint),
			'all'
		);

		$indices = $this->parseSelection($selection, $count);
		$selected = [];
		foreach ($indices AS $index)
		{
			$selected[] = $replacements[$index];
		}

		return $selected;
	}

	protected function parseSelection(string $input, int $count): array
	{
		$input = trim($input);

		if (strtolower($input) === 'none' || $input === '')
		{
			return [];
		}

		if (strtolower($input) === 'all')
		{
			return range(0, $count - 1);
		}

		$indices = [];
		$parts = preg_split('/\s*,\s*/', $input);
		foreach ($parts AS $part)
		{
			if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $matches))
			{
				$start = intval($matches[1]);
				$end = intval($matches[2]);
				for ($i = $start; $i <= $end; $i++)
				{
					if ($i >= 1 && $i <= $count)
					{
						$indices[] = $i - 1;
					}
				}
			}
			else if (preg_match('/^\d+$/', $part))
			{
				$num = intval($part);
				if ($num >= 1 && $num <= $count)
				{
					$indices[] = $num - 1;
				}
			}
		}

		$indices = array_unique($indices);
		sort($indices);

		return $indices;
	}

	protected function renderContextPreview(SymfonyStyle $io, string $targetLabel, string $originalContent, array $replacements, int $previewLines): void
	{
		$io->section("Preview: $targetLabel");

		$mutatedContent = $originalContent;
		foreach ($replacements AS $i => $replacement)
		{
			$original = $replacement['original'];
			$code = $replacement['code'];

			$before = $this->extractContextSnippet($mutatedContent, $original, $previewLines);
			$afterContent = $this->replaceFirst($mutatedContent, $original, $code);
			$after = $this->extractContextSnippet($afterContent, $code, $previewLines);

			$io->writeln(sprintf(' %d. <comment>%s%s</comment>', $i + 1, $replacement['name'], $replacement['existingName'] ? ' (existing)' : ' (new)'));
			$io->writeln('    <info>Before:</info>');
			$this->writeHighlightedBlock($io, $before, $original, 'red', '    ');
			$io->writeln('    <info>After:</info>');
			$this->writeHighlightedBlock($io, $after, $code, 'green', '    ');

			$mutatedContent = $afterContent;

			if ($i < count($replacements) - 1)
			{
				$io->newLine();
			}
		}
	}

	protected function applyReplacementsToContent(string $content, array $replacements): string
	{
		foreach ($replacements AS $replacement)
		{
			$content = $this->replaceFirst($content, $replacement['original'], $replacement['code']);
		}

		return $content;
	}

	protected function writeEscapedBlock(SymfonyStyle $io, string $text): void
	{
		$lines = explode("\n", $text);
		foreach ($lines AS $line)
		{
			$io->writeln(OutputFormatter::escape($line));
		}
	}

	protected function writeHighlightedBlock(SymfonyStyle $io, string $text, ?string $highlight, string $color, string $linePrefix = ''): void
	{
		$escapedText = OutputFormatter::escape($text);
		if ($highlight !== null && $highlight !== '')
		{
			$escapedHighlight = OutputFormatter::escape($highlight);
			$escapedText = $this->replaceFirst(
				$escapedText,
				$escapedHighlight,
				sprintf('<fg=%s;options=bold>%s</>', $color, $escapedHighlight)
			);
		}

		$lines = explode("\n", $escapedText);
		foreach ($lines AS $line)
		{
			$io->writeln($linePrefix . $line);
		}
	}

	protected function extractContextSnippet(string $content, string $needle, int $previewLines): string
	{
		$lines = preg_split('/\R/', $content);
		[$lineIndex, $needleLineCount] = $this->findLineContaining($lines, $needle);
		if ($lineIndex === null)
		{
			$sliceSize = max(1, $previewLines * 2 + 1);
			return implode("\n", array_slice($lines, 0, $sliceSize));
		}

		$start = max(0, $lineIndex - $previewLines);
		$lastNeedleLine = $lineIndex + $needleLineCount - 1;
		$end = min(count($lines) - 1, $lastNeedleLine + $previewLines);
		$snippetLines = [];
		for ($i = $start; $i <= $end; $i++)
		{
			$snippetLines[] = $lines[$i];
		}

		// trim leading and trailing empty lines for consistent spacing
		while ($snippetLines && trim($snippetLines[0]) === '')
		{
			array_shift($snippetLines);
		}
		while ($snippetLines && trim($snippetLines[count($snippetLines) - 1]) === '')
		{
			array_pop($snippetLines);
		}

		return implode("\n", $snippetLines);
	}

	/**
	 * @return array{?int, int} Line index and number of lines the needle spans
	 */
	protected function findLineContaining(array $lines, string $needle): array
	{
		if ($needle === '')
		{
			return [null, 1];
		}

		// single-line needle: check each line individually
		if (strpos($needle, "\n") === false && strpos($needle, "\r") === false)
		{
			foreach ($lines AS $i => $line)
			{
				if (strpos($line, $needle) !== false)
				{
					return [$i, 1];
				}
			}

			return [null, 1];
		}

		// multi-line needle: search by joining lines from each starting position
		$needleLineCount = count(preg_split('/\R/', $needle));
		$lineCount = count($lines);
		for ($i = 0; $i <= $lineCount - $needleLineCount; $i++)
		{
			$candidate = implode("\n", array_slice($lines, $i, $needleLineCount));
			if (strpos($candidate, $needle) !== false)
			{
				return [$i, $needleLineCount];
			}
		}

		return [null, $needleLineCount];
	}

	protected function replaceFirst(string $haystack, string $needle, string $replace): string
	{
		$pos = strpos($haystack, $needle);
		if ($pos === false)
		{
			return $haystack;
		}

		return substr($haystack, 0, $pos) . $replace . substr($haystack, $pos + strlen($needle));
	}
}
