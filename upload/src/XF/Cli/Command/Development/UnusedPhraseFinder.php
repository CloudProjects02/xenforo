<?php

namespace XF\Cli\Command\Development;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Cli\Command\AbstractCommand;
use XF\Entity\AddOn;
use XF\Finder\PhraseFinder;
use XF\Service\Development\UnusedPhraseFinderService;

use function count, in_array, intval;

class UnusedPhraseFinder extends AbstractCommand
{
	use RequiresDevModeTrait;

	protected function configure(): void
	{
		$this
			->setName('xf-dev:unused-phrase-finder')
			->setDescription('Finds and optionally deletes unused master phrases for an add-on')
			->addOption('addon', null, InputOption::VALUE_REQUIRED, 'Add-on ID to process')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
			->addOption('show-unknown', null, InputOption::VALUE_NONE, 'Show unknown used phrase titles in text mode')
			->addOption('delete', null, InputOption::VALUE_REQUIRED, 'Comma-separated phrase IDs or titles to delete (unused only)')
			->addOption('delete-all', null, InputOption::VALUE_NONE, 'Delete all currently unused phrases')
			->addOption('yes', null, InputOption::VALUE_NONE, 'Skip confirmation prompts');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$json = $input->getOption('json');

		$addOnId = $input->getOption('addon');
		$addOn = \XF::em()->find(AddOn::class, $addOnId);
		if (!$addOn)
		{
			$message = 'Invalid or missing --addon value.';
			if ($json)
			{
				$this->outputJson($output, ['command' => 'xf-dev:unused-phrase-finder', 'errors' => [$message]]);
				return Command::FAILURE;
			}
			$io->error($message);
			return Command::FAILURE;
		}

		$delete = $input->getOption('delete');
		$deleteAll = $input->getOption('delete-all');
		if ($delete && $deleteAll)
		{
			$message = 'Use either --delete or --delete-all, not both.';
			if ($json)
			{
				$this->outputJson($output, ['command' => 'xf-dev:unused-phrase-finder', 'addonId' => $addOnId, 'errors' => [$message]]);
				return Command::FAILURE;
			}
			$io->error($message);
			return Command::FAILURE;
		}

		$service = \XF::app()->service(UnusedPhraseFinderService::class, $addOn);
		$results = $service->findAll();
		$unusedTitles = $results['unused'];
		$unknownTitles = $results['unknown'];

		$unusedPhrases = [];
		if ($unusedTitles)
		{
			$unusedPhrases = \XF::finder(PhraseFinder::class)
				->where('title', $unusedTitles)
				->where('addon_id', $addOnId)
				->where('language_id', 0)
				->orderTitle()
				->fetch();
		}

		$deletedCount = 0;
		$errors = [];

		$shouldDelete = (bool) ($delete || $deleteAll);
		if ($shouldDelete)
		{
			$toDelete = $deleteAll ? $unusedPhrases : $this->resolveDeleteSelection($delete, $unusedPhrases);

			if (!$toDelete || !count($toDelete))
			{
				if (!$json)
				{
					$io->warning('No matching unused phrases were selected for deletion.');
				}
			}
			else
			{
				$yes = (bool) $input->getOption('yes');
				if ($json && !$yes)
				{
					$errors[] = 'JSON delete mode requires --yes to avoid interactive confirmation.';
					$confirmed = false;
				}
				else
				{
					if (!$json && !$yes)
					{
						$io->section('Phrases to delete');
						$rows = [];
						foreach ($toDelete AS $phrase)
						{
							$rows[] = [$phrase->title];
						}
						$io->table(['Phrase title'], $rows);
					}

					$confirmed = $yes || $io->confirm(
						sprintf('Delete %d unused phrases from %s?', count($toDelete), $addOnId),
						false
					);
				}

				if ($confirmed)
				{
					foreach ($toDelete AS $phrase)
					{
						try
						{
							$phrase->delete();
							$deletedCount++;
						}
						catch (\Throwable $e)
						{
							$errors[] = $phrase->title . ': ' . $e->getMessage();
						}
					}
				}
				else if (!$json)
				{
					$io->warning('No changes applied.');
				}
			}
		}

		if ($deletedCount > 0)
		{
			$results = $service->findAll();
			$unusedTitles = $results['unused'];
			$unknownTitles = $results['unknown'];
		}

		if (!$json)
		{
			$io->title('Unused phrase finder');
			$io->text("Add-on: $addOnId");
			$io->newLine();

			$io->table(['Metric', 'Value'], [
				['Unused phrases', count($unusedTitles)],
				['Unknown used phrases', count($unknownTitles)],
				['Deleted', $deletedCount],
			]);

			if ($unusedTitles)
			{
				$rows = [];
				foreach ($unusedTitles AS $title)
				{
					$rows[] = [$title];
				}
				$io->table(['Unused phrase titles'], $rows);
			}
			else
			{
				$io->success('No unused phrases detected.');
			}

			if ($input->getOption('show-unknown') && $unknownTitles)
			{
				$rows = [];
				foreach ($unknownTitles AS $title)
				{
					$rows[] = [$title];
				}
				$io->table(['Unknown used phrases'], $rows);
			}

			if ($errors)
			{
				$io->error($errors);
			}
		}

		$payload = [
			'command' => 'xf-dev:unused-phrase-finder',
			'addonId' => $addOnId,
			'unusedCount' => count($unusedTitles),
			'unknownCount' => count($unknownTitles),
			'unused' => array_values($unusedTitles),
			'unknown' => array_values($unknownTitles),
			'deletedCount' => $deletedCount,
			'errors' => $errors,
		];
		if ($json)
		{
			$this->outputJson($output, $payload);
		}

		return $errors ? Command::FAILURE : Command::SUCCESS;
	}

	protected function resolveDeleteSelection(string $deleteOption, iterable $unusedPhrases): array
	{
		$terms = array_filter(array_map('trim', explode(',', $deleteOption)), function ($term)
		{
			return $term !== '';
		});
		if (!$terms)
		{
			return [];
		}

		$byId = [];
		$byTitle = [];
		foreach ($terms AS $term)
		{
			if (preg_match('/^\d+$/', $term))
			{
				$byId[] = intval($term);
			}
			else
			{
				$byTitle[] = $term;
			}
		}

		$selected = [];
		foreach ($unusedPhrases AS $phrase)
		{
			if (($byId && in_array($phrase->phrase_id, $byId, true)) || ($byTitle && in_array($phrase->title, $byTitle, true)))
			{
				$selected[] = $phrase;
			}
		}

		return $selected;
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
}
