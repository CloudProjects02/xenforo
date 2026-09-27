<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Template;
use XF\Finder\TemplateFinder;
use XF\Util\Str;

use function array_slice, count, in_array;

class TemplateMakeCommand extends AbstractMakeCommand
{
	protected const VALID_TYPES = ['public', 'admin', 'email'];

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:template')
			->setDescription('Create a template in the master style')
			->addArgument(
				'title',
				InputArgument::REQUIRED,
				'The template title (e.g. "my_addon_page" or "widget_sidebar")'
			)
			->addOption(
				'type',
				't',
				InputOption::VALUE_REQUIRED,
				'Template type: public, admin, email',
				'public'
			)
			->addOption(
				'content',
				'c',
				InputOption::VALUE_REQUIRED,
				'Template content (optional)'
			)
			->addOption(
				'content-file',
				null,
				InputOption::VALUE_REQUIRED,
				'Read template content from a file'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->parseTemplateSyntax($input, (string) $input->getArgument('title'));

		$title = $input->getArgument('title');

		if ($title)
		{
			$this->parseTemplateSyntax($input, $title);
		}

		if (!$input->getOption('addon'))
		{
			$addOnId = $this->promptForAddOn($io);
			$input->setOption('addon', $addOnId);
		}

		if (!$input->getArgument('title'))
		{
			$title = $io->ask(
				'Enter the template title',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Template title cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('title', $title);
		}
	}

	protected function parseTemplateSyntax(InputInterface $input, string $value): void
	{
		$parts = explode(':', $value);

		if (count($parts) === 1)
		{
			return;
		}

		if (count($parts) === 2)
		{
			if (in_array($parts[0], self::VALID_TYPES, true))
			{
				$input->setOption('type', $parts[0]);
				$input->setArgument('title', $parts[1]);
			}
			else
			{
				$input->setOption('addon', $parts[0]);
				$input->setArgument('title', $parts[1]);
			}
			return;
		}

		if (count($parts) === 3)
		{
			$input->setOption('addon', $parts[0]);
			$input->setOption('type', $parts[1]);
			$input->setArgument('title', $parts[2]);
			return;
		}

		if (in_array($parts[0], self::VALID_TYPES, true))
		{
			$input->setOption('type', $parts[0]);
			$input->setArgument('title', implode(':', array_slice($parts, 1)));
		}
		else
		{
			$input->setOption('addon', $parts[0]);
			if (in_array($parts[1], self::VALID_TYPES, true))
			{
				$input->setOption('type', $parts[1]);
				$input->setArgument('title', implode(':', array_slice($parts, 2)));
			}
			else
			{
				$input->setArgument('title', implode(':', array_slice($parts, 1)));
			}
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->parseTemplateSyntax($input, (string) $input->getArgument('title'));

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
		$type = $input->getOption('type');
		if ($input->getOption('content') === null && $input->getOption('content-file') === null)
		{
			$content = '';
		}
		else
		{
			$content = $this->readTextOption($input, 'content', 'content-file', $io);
			if ($content === null)
			{
				return Command::FAILURE;
			}
		}
		$force = $input->getOption('force');

		if (!$this->validateType($type, $io))
		{
			return Command::FAILURE;
		}

		if (!$this->validateTitle($title, $io))
		{
			return Command::FAILURE;
		}

		$existing = $this->getExistingTemplate($type, $title);
		$isUpdate = false;

		if ($existing)
		{
			if (!$this->validateEntityOwner($existing, $io))
			{
				return Command::FAILURE;
			}
			if (!$force)
			{
				$io->error("Template '$type:$title' already exists.");
				$io->note('Use --force to update the existing template.');
				return Command::FAILURE;
			}

			$template = $existing;
			$isUpdate = true;
			$io->note("Updating existing template '$type:$title'");
		}
		else
		{
			$template = \XF::em()->create(Template::class);
			$template->type = $type;
			$template->title = $title;
			$template->style_id = 0;
			$template->addon_id = $this->addOnId;
		}

		if (!$template->set('template', $content))
		{
			$io->error($template->getErrors());
			return Command::FAILURE;
		}
		$template->save();

		$action = $isUpdate ? 'updated' : 'created';
		$io->success("Template '$type:$title' $action.");

		$io->table(
			['Property', 'Value'],
			[
				['Type', $type],
				['Title', $title],
				['Add-on', $this->addOnId],
				['Content', $content === '' ? '(empty)' : $this->truncateText($content, 50)],
			]
		);

		return Command::SUCCESS;
	}

	protected function validateType(string $type, SymfonyStyle $io): bool
	{
		if (!in_array($type, self::VALID_TYPES, true))
		{
			$io->error("Invalid template type '$type'. Valid types: " . implode(', ', self::VALID_TYPES));
			return false;
		}

		return true;
	}

	protected function validateTitle(string $title, SymfonyStyle $io): bool
	{
		if (!preg_match('/^[a-z0-9_.]+$/i', $title))
		{
			$io->error('Template title may only contain letters, numbers, underscores, and dots.');
			return false;
		}

		if (Str::strlen($title) > 100)
		{
			$io->error('Template title must be 100 characters or less.');
			return false;
		}

		if (strpos($title, '.') === 0)
		{
			$io->error('Template title cannot start with a dot.');
			return false;
		}

		if (strpos($title, '..') !== false)
		{
			$io->error('Template title cannot contain consecutive dots (..).');
			return false;
		}

		if (preg_match('/\.html$/i', $title))
		{
			$io->error('Template title cannot end with .html.');
			return false;
		}

		return true;
	}

	protected function getExistingTemplate(string $type, string $title): ?Template
	{
		return \XF::finder(TemplateFinder::class)
			->where('type', $type)
			->where('title', $title)
			->where('style_id', 0)
			->fetchOne();
	}
}
