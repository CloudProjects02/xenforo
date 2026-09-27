<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Template;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\Development\TemplatePhraserService;

use function count, intval;

class TemplatePhraser extends AbstractPhraserCommand
{
	use RequiresDevModeTrait;

	protected function configure(): void
	{
		$this
			->setName('xf-dev:template-phraser')
			->setAliases(['xf-dev:phraser-template'])
			->setDescription('Analyzes and applies phrase replacements in templates')
			->addOption('addon', null, InputOption::VALUE_REQUIRED, 'Add-on ID to process')
			->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template in type:title format')
			->addOption('all', null, InputOption::VALUE_NONE, 'Process all phrasable templates for the add-on (default when --template is omitted)')
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
				$this->outputJson($output, ['command' => 'xf-dev:template-phraser', 'errors' => [$message]]);
				return Command::FAILURE;
			}
			$io->error($message);
			return Command::FAILURE;
		}

		$templateTarget = $input->getOption('template');

		$previewLines = max(0, intval($input->getOption('preview-lines')));
		$phrasePrefix = $input->getOption('phrase-prefix');
		if ($phrasePrefix === null)
		{
			$phrasePrefix = $this->getDefaultPhrasePrefix($addOnId);
		}

		$service = \XF::app()->service(TemplatePhraserService::class);
		$service->setAddOnId($addOnId, $phrasePrefix);

		$templates = $templateTarget
			? $this->getSingleTemplate($templateTarget, $addOnId)
			: $this->getAllTemplates($addOnId, $service);
		if ($templateTarget && $templates === null)
		{
			$message = 'Template not found for --template in this add-on.';
			if ($json)
			{
				$this->outputJson($output, ['command' => 'xf-dev:template-phraser', 'addonId' => $addOnId, 'errors' => [$message]]);
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

		foreach ($templates AS $template)
		{
			$totals['targetsScanned']++;
			try
			{
				$replaceData = [];
				$processed = $service->phraseTemplate($template->template, $replaceData);
				$replacements = $this->normalizeTemplateReplacements($replaceData);
				if ($replacements)
				{
					$totals['targetsChanged']++;
					$totals['replacementsFound'] += count($replacements);
				}

				$results[] = [
					'template' => $template,
					'target' => $template->combined_title,
					'originalContent' => $template->template,
					'processedContent' => $processed,
					'replacements' => $replacements,
				];
			}
			catch (\Throwable $e)
			{
				$errors[] = $template->combined_title . ': ' . $e->getMessage();
			}
		}

		$apply = !$input->getOption('dry-run');
		$yes = $input->getOption('yes');
		$mode = $apply ? 'apply' : 'analyze';

		if (!$json)
		{
			$io->title('Template phraser');
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
							$service->applyTemplateChanges($result['template'], $result['processedContent'], $result['replacements']);
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
			'command' => 'xf-dev:template-phraser',
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

	protected function getAllTemplates(string $addOnId, TemplatePhraserService $service): AbstractCollection
	{
		$finder = \XF::finder(Template::class)->where([
			'style_id' => 0,
			'addon_id' => $addOnId,
		]);
		$finder->order('type');
		$finder->orderTitle();

		$templates = $finder->fetch();
		return $templates->filter(function (Template $template) use ($service)
		{
			return $service->isTemplatePhrasable($template);
		});
	}

	protected function getSingleTemplate(string $templateArg, string $addOnId): ?array
	{
		$parts = explode(':', $templateArg, 2);
		if (count($parts) === 1)
		{
			$type = 'public';
			$title = $parts[0];
		}
		else
		{
			$type = $parts[0];
			$title = $parts[1];
		}

		$template = \XF::em()->findOne(Template::class, [
			'style_id' => 0,
			'addon_id' => $addOnId,
			'type' => $type,
			'title' => $title,
		]);
		if (!$template)
		{
			return null;
		}

		return [$template];
	}
}
