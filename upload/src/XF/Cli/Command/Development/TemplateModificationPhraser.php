<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\TemplateModification;
use XF\Finder\TemplateModificationFinder;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\Development\TemplateModificationPhraserService;

use function count, intval;

class TemplateModificationPhraser extends AbstractPhraserCommand
{
	use RequiresDevModeTrait;

	protected function configure(): void
	{
		$this
			->setName('xf-dev:template-modification-phraser')
			->setAliases(['xf-dev:phraser-template-modification'])
			->setDescription('Analyzes and applies phrase replacements in template modifications')
			->addOption('addon', null, InputOption::VALUE_REQUIRED, 'Add-on ID to process')
			->addOption('modification', null, InputOption::VALUE_REQUIRED, 'Modification id or type:key')
			->addOption('all', null, InputOption::VALUE_NONE, 'Process all phrasable template modifications for the add-on (default when --modification is omitted)')
			->addOption('phrase-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix for generated phrase names')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyze and preview only; do not apply changes')
			->addOption('yes', null, InputOption::VALUE_NONE, 'Skip confirmation prompts')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
			->addOption('preview-lines', null, InputOption::VALUE_REQUIRED, 'Context lines around each change', 2);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$json = $input->getOption('json');

		$addOnId = (string) ($input->getOption('addon') ?? '');
		$addOn = $this->getAddOnById($addOnId);
		if (!$addOn)
		{
			$message = 'Invalid or missing --addon value.';
			if ($json)
			{
				$this->outputJson($output, ['command' => 'xf-dev:template-modification-phraser', 'errors' => [$message]]);
				return Command::FAILURE;
			}
			$io->error($message);
			return Command::FAILURE;
		}

		$modificationTarget = $input->getOption('modification');

		$previewLines = max(0, intval($input->getOption('preview-lines')));
		$phrasePrefix = $input->getOption('phrase-prefix');
		if ($phrasePrefix === null)
		{
			$phrasePrefix = $this->getDefaultPhrasePrefix($addOnId);
		}

		$service = \XF::app()->service(TemplateModificationPhraserService::class);
		$service->setAddOnId($addOnId, $phrasePrefix);

		$templateMods = $modificationTarget
			? $this->getSingleTemplateMod($modificationTarget, $addOnId)
			: $this->getAllTemplateMods($addOnId, $service);
		if ($modificationTarget && $templateMods === null)
		{
			$message = 'Template modification not found for --modification in this add-on.';
			if ($json)
			{
				$this->outputJson($output, ['command' => 'xf-dev:template-modification-phraser', 'addonId' => $addOnId, 'errors' => [$message]]);
				return Command::FAILURE;
			}
			$io->error($message);
			return Command::FAILURE;
		}

		$results = [];
		$errors = [];
		$totals = [
			'targetsScanned' => 0,
			'targetsChanged' => 0,
			'replacementsFound' => 0,
			'replacementsApplied' => 0,
			'phrasesCreated' => 0,
			'phrasesReused' => 0,
		];

		foreach ($templateMods AS $templateMod)
		{
			$target = $templateMod->type . ':' . $templateMod->modification_key;
			$totals['targetsScanned']++;
			try
			{
				$replaceData = [];
				$processed = $service->phraseTemplate($templateMod->replace, $replaceData);
				$replacements = $this->normalizeTemplateReplacements($replaceData);
				if ($replacements)
				{
					$totals['targetsChanged']++;
					$totals['replacementsFound'] += count($replacements);
				}

				$results[] = [
					'templateMod' => $templateMod,
					'target' => $target,
					'originalContent' => $templateMod->replace,
					'processedContent' => $processed,
					'replacements' => $replacements,
				];
			}
			catch (\Throwable $e)
			{
				$errors[] = $target . ': ' . $e->getMessage();
			}
		}

		$apply = !$input->getOption('dry-run');
		$yes = $input->getOption('yes');
		$mode = $apply ? 'apply' : 'analyze';

		if (!$json)
		{
			$io->title('Template modification phraser');
			$io->text("Add-on: $addOnId");
			$io->text('Phrase prefix: ' . ($phrasePrefix === '' ? '(none)' : $phrasePrefix));
			$io->newLine();
		}

		if ($totals['replacementsFound'] > 0)
		{
			foreach ($results AS &$result)
			{
				if (!$result['replacements'])
				{
					continue;
				}

				if (!$json)
				{
					$this->renderContextPreview(
						$io,
						$result['target'],
						$result['originalContent'],
						$result['replacements'],
						$previewLines
					);
				}

				if ($apply && !$yes && !$json)
				{
					$selected = $this->selectReplacements($io, $result['replacements']);
					$result['replacements'] = $selected;
					$result['processedContent'] = $this->applyReplacementsToContent($result['originalContent'], $selected);
				}
			}
			unset($result);

			if ($apply)
			{
				if ($json && !$yes)
				{
					$errors[] = 'JSON apply mode requires --yes to avoid interactive confirmation.';
					$mode = 'analyze';
				}
				else
				{
					foreach ($results AS $result)
					{
						if (!$result['replacements'])
						{
							continue;
						}

						try
						{
							$service->applyTemplateChanges($result['templateMod'], $result['processedContent'], $result['replacements']);
							$totals['replacementsApplied'] += count($result['replacements']);
							foreach ($result['replacements'] AS $replacement)
							{
								if ($replacement['existingName'] === $replacement['name'])
								{
									$totals['phrasesReused']++;
								}
								else
								{
									$totals['phrasesCreated']++;
								}
							}
						}
						catch (\Throwable $e)
						{
							$errors[] = $result['target'] . ': ' . $e->getMessage();
						}
					}
				}
			}
		}

		if (!$json)
		{
			$io->table(
				['Metric', 'Value'],
				[
					['Mode', $mode],
					['Targets scanned', $totals['targetsScanned']],
					['Targets with replacements', $totals['targetsChanged']],
					['Replacements found', $totals['replacementsFound']],
					['Replacements applied', $totals['replacementsApplied']],
					['Phrases created', $totals['phrasesCreated']],
					['Phrases reused', $totals['phrasesReused']],
				]
			);

			if ($errors)
			{
				$io->error($errors);
			}
			else
			{
				$io->success('Completed.');
			}
		}

		$payload = [
			'command' => 'xf-dev:template-modification-phraser',
			'addonId' => $addOnId,
			'mode' => $mode,
			'targetsScanned' => $totals['targetsScanned'],
			'targetsChanged' => $totals['targetsChanged'],
			'replacementsFound' => $totals['replacementsFound'],
			'replacementsApplied' => $totals['replacementsApplied'],
			'phrasesCreated' => $totals['phrasesCreated'],
			'phrasesReused' => $totals['phrasesReused'],
			'errors' => $errors,
		];

		if ($json)
		{
			$this->outputJson($output, $payload);
		}

		return $errors ? Command::FAILURE : Command::SUCCESS;
	}

	protected function getAllTemplateMods(string $addOnId, TemplateModificationPhraserService $service): AbstractCollection
	{
		$finder = \XF::finder(TemplateModificationFinder::class)->where('addon_id', $addOnId);
		$finder->order('type');
		$finder->order('modification_key');
		$templateMods = $finder->fetch();

		return $templateMods->filter(function (TemplateModification $templateMod) use ($service)
		{
			return $service->isTemplateModificationPhrasable($templateMod);
		});
	}

	protected function getSingleTemplateMod(string $target, string $addOnId): ?array
	{
		if (preg_match('/^\d+$/', $target))
		{
			$templateMod = \XF::em()->find(TemplateModification::class, intval($target));
		}
		else
		{
			$parts = explode(':', $target, 2);
			if (count($parts) == 2)
			{
				$templateMod = \XF::em()->findOne(TemplateModification::class, [
					'addon_id' => $addOnId,
					'type' => $parts[0],
					'modification_key' => $parts[1],
				]);
			}
			else
			{
				$templateMod = \XF::em()->findOne(TemplateModification::class, [
					'addon_id' => $addOnId,
					'modification_key' => $target,
				]);
			}
		}

		if (!$templateMod || $templateMod->addon_id !== $addOnId)
		{
			return null;
		}

		return [$templateMod];
	}
}
