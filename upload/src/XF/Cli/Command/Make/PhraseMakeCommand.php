<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Phrase;
use XF\Finder\PhraseFinder;

use function strlen;

class PhraseMakeCommand extends AbstractMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:phrase')
			->setDescription('Create a phrase in the master language')
			->addArgument(
				'title',
				InputArgument::REQUIRED,
				'The phrase title (e.g. "my_addon_welcome" or "my_group.welcome_message")'
			)
			->addOption(
				'text',
				't',
				InputOption::VALUE_REQUIRED,
				'The phrase text content'
			)
			->addOption(
				'text-file',
				null,
				InputOption::VALUE_REQUIRED,
				'Read phrase text from a file'
			)
			->addOption(
				'global-cache',
				null,
				InputOption::VALUE_NONE,
				'Mark the phrase as globally cached'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'title');

		$this->interactAddOn($input, $io, 'title', true);

		if (!$input->getArgument('title'))
		{
			$title = $io->ask(
				'Enter the phrase title',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Phrase title cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('title', $title);
		}

		if ($input->getOption('text') === null && $input->getOption('text-file') === null)
		{
			$text = $io->ask('Enter the phrase text');
			$input->setOption('text', $text ?? '');
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'title');

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io))
		{
			return Command::FAILURE;
		}

		$title = $input->getArgument('title');

		if (!$this->validateTitle($title, $io))
		{
			return Command::FAILURE;
		}

		if ($input->getOption('text') === null && $input->getOption('text-file') === null)
		{
			$text = '';
		}
		else
		{
			$text = $this->readTextOption($input, 'text', 'text-file', $io);
			if ($text === null)
			{
				return Command::FAILURE;
			}
		}
		$force = $input->getOption('force');
		$globalCache = $input->getOption('global-cache');

		$existing = $this->getExistingPhrase($title);
		$isUpdate = false;

		if ($existing)
		{
			if (!$this->validateEntityOwner($existing, $io))
			{
				return Command::FAILURE;
			}
			if (!$force)
			{
				$io->error("Phrase '$title' already exists.");
				$io->note('Use --force to update the existing phrase.');
				return Command::FAILURE;
			}

			$phrase = $existing;
			$isUpdate = true;
			$io->note("Updating existing phrase '$title'");
		}
		else
		{
			$phrase = \XF::em()->create(Phrase::class);
			$phrase->language_id = 0;
			$phrase->title = $title;
			$phrase->addon_id = $this->addOnId;
		}

		$phrase->phrase_text = $text;
		$phrase->global_cache = $globalCache;
		$phrase->save();

		$action = $isUpdate ? 'updated' : 'created';
		$io->success("Phrase '$title' $action.");

		$io->table(
			['Property', 'Value'],
			[
				['Title', $title],
				['Text', $this->truncateText($text, 60)],
				['Add-on', $this->addOnId],
				['Global Cache', $globalCache ? 'Yes' : 'No'],
			]
		);

		return Command::SUCCESS;
	}

	protected function validateTitle(string $title, SymfonyStyle $io): bool
	{
		if (!preg_match('/^[a-z0-9_.]+$/i', $title))
		{
			$io->error('Phrase title may only contain letters, numbers, dots, and underscores.');
			return false;
		}

		if (strlen($title) > 100)
		{
			$io->error('Phrase title must be 100 characters or less.');
			return false;
		}

		if (strpos($title, '.') === 0)
		{
			$io->error('Phrase title cannot start with a dot.');
			return false;
		}

		if (strpos($title, '.') !== false)
		{
			$dotCount = substr_count($title, '.');
			if ($dotCount > 1)
			{
				$io->error('Phrase title may only contain a single dot character.');
				return false;
			}

			if (substr($title, -1) === '.')
			{
				$io->error('Phrase title cannot end with a dot.');
				return false;
			}

			$parts = explode('.', $title);
			if (strlen($parts[0]) > 50)
			{
				$io->error('Phrase group prefix (before the dot) must be 50 characters or less.');
				return false;
			}
		}

		return true;
	}

	protected function getExistingPhrase(string $title): ?Phrase
	{
		return \XF::finder(PhraseFinder::class)
			->where('title', $title)
			->where('language_id', 0)
			->fetchOne();
	}
}
