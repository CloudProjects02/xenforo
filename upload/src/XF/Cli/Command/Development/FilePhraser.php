<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Service\Development\FilePhraserService;

use function count, intval;

class FilePhraser extends AbstractPhraserCommand
{
	use RequiresDevModeTrait;

	protected function configure(): void
	{
		$this
			->setName('xf-dev:file-phraser')
			->setAliases(['xf-dev:phraser-file'])
			->setDescription('Analyzes and applies phrase replacements in add-on PHP files')
			->addOption('addon', null, InputOption::VALUE_REQUIRED, 'Add-on ID to process')
			->addOption('file', null, InputOption::VALUE_REQUIRED, 'Single file path relative to the add-on root')
			->addOption('all', null, InputOption::VALUE_NONE, 'Process all phrasable files for the add-on (default when --file is omitted)')
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
				$this->outputJson($output, [
					'command' => 'xf-dev:file-phraser',
					'errors' => [$message],
				]);

				return Command::FAILURE;
			}
			$io->error($message);

			return Command::FAILURE;
		}

		$fileTarget = $input->getOption('file');

		$previewLines = max(0, intval($input->getOption('preview-lines')));
		$phrasePrefix = $input->getOption('phrase-prefix');
		if ($phrasePrefix === null)
		{
			$phrasePrefix = $this->getDefaultPhrasePrefix($addOnId);
		}

		$service = \XF::app()->service(FilePhraserService::class, $addOn);
		$service->setPhrasePrefix($phrasePrefix);
		$apply = !$input->getOption('dry-run');

		$targets = $fileTarget ? [$fileTarget] : array_keys($service->getPhrasableFileList());

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

		foreach ($targets AS $file)
		{
			$totals['targetsScanned']++;

			if (!$service->isProcessableFile($file, $error, $fullPath, $apply))
			{
				$errors[] = $file . ': ' . $error;
				continue;
			}

			try
			{
				$rawReplacements = $service->analyzeFile($file);
				$replacements = [];
				foreach ($rawReplacements AS $original => $replacement)
				{
					$replacement['original'] = $original;
					$replacements[] = $replacement;
				}

				if ($replacements)
				{
					$totals['targetsChanged']++;
					$totals['replacementsFound'] += count($replacements);
				}

				$results[] = [
					'target' => $file,
					'displayTarget' => $service->getPrintableFileName($file),
					'fullPath' => $fullPath,
					'originalContent' => file_get_contents($fullPath),
					'replacements' => $replacements,
				];
			}
			catch (\Throwable $e)
			{
				$errors[] = $file . ': ' . $e->getMessage();
			}
		}

		$yes = $input->getOption('yes');
		$mode = $apply ? 'apply' : 'analyze';

		if (!$json)
		{
			$io->title('File phraser');
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
					$this->renderContextPreview($io, $result['displayTarget'], $result['originalContent'], $result['replacements'], $previewLines);
				}

				if ($apply && !$yes && !$json)
				{
					$selected = $this->selectReplacements($io, $result['replacements']);
					$result['replacements'] = $selected;
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
							$service->applyFileChanges($result['target'], $result['replacements']);
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
				[
					'Metric',
					'Value',
				],
				[
					[
						'Mode',
						$mode,
					],
					[
						'Targets scanned',
						$totals['targetsScanned'],
					],
					[
						'Targets with replacements',
						$totals['targetsChanged'],
					],
					[
						'Replacements found',
						$totals['replacementsFound'],
					],
					[
						'Replacements applied',
						$totals['replacementsApplied'],
					],
					[
						'Phrases created',
						$totals['phrasesCreated'],
					],
					[
						'Phrases reused',
						$totals['phrasesReused'],
					],
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
			'command' => 'xf-dev:file-phraser',
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
}
