<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\AdminNavigation;
use XF\Finder\AdminNavigationFinder;

use function strlen;

class AdminNavigationMakeCommand extends AbstractMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:admin-navigation')
			->setDescription('Create an admin navigation entry')
			->addArgument(
				'id',
				InputArgument::REQUIRED,
				'The navigation ID (e.g. "myNavEntry" or "Vendor/Addon:myNavEntry")'
			)
			->addOption(
				'parent',
				'p',
				InputOption::VALUE_REQUIRED,
				'Parent navigation ID',
				''
			)
			->addOption(
				'title',
				't',
				InputOption::VALUE_REQUIRED,
				'Navigation title phrase text'
			)
			->addOption(
				'link',
				'l',
				InputOption::VALUE_REQUIRED,
				'Route link',
				''
			)
			->addOption(
				'icon',
				'i',
				InputOption::VALUE_REQUIRED,
				'FontAwesome icon class (e.g. "fa-cloud")',
				''
			)
			->addOption(
				'order',
				'o',
				InputOption::VALUE_REQUIRED,
				'Display order',
				'10'
			)
			->addOption(
				'permission',
				null,
				InputOption::VALUE_REQUIRED,
				'Admin permission ID',
				''
			)
			->addOption(
				'hide-no-children',
				null,
				InputOption::VALUE_NONE,
				'Hide this entry if it has no visible children'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'id');

		$this->interactAddOn($input, $io, 'id', true);

		if (!$input->getArgument('id'))
		{
			$id = $io->ask(
				'Enter the navigation ID (e.g. myNavEntry)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Navigation ID cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('id', $id);
		}

		if ($input->getOption('title') === null)
		{
			$title = $io->ask('Enter the navigation title');
			$input->setOption('title', $title ?? '');
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'id');

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

		$id = $input->getArgument('id');

		if (!$this->validateId($id, $io))
		{
			return Command::FAILURE;
		}

		$title = $input->getOption('title') ?? '';
		$parent = $input->getOption('parent');
		$link = $input->getOption('link');
		$icon = $input->getOption('icon');
		$order = (int) $input->getOption('order');
		$permission = $input->getOption('permission');
		$hideNoChildren = $input->getOption('hide-no-children');
		$force = $input->getOption('force');

		$existing = $this->getExistingNavigation($id);
		$isUpdate = false;

		if ($existing)
		{
			if (!$this->validateEntityOwner($existing, $io))
			{
				return Command::FAILURE;
			}
			if (!$force)
			{
				$io->error("Admin navigation entry '$id' already exists.");
				$io->note('Use --force to update the existing entry.');
				return Command::FAILURE;
			}

			$navigation = $existing;
			$isUpdate = true;
			$io->note("Updating existing admin navigation entry '$id'");
		}
		else
		{
			$navigation = \XF::em()->create(AdminNavigation::class);
			$navigation->navigation_id = $id;
			$navigation->addon_id = $this->addOnId;
		}

		$navigation->parent_navigation_id = $parent;
		$navigation->display_order = $order;
		$navigation->link = $link;
		$navigation->icon = $icon;
		$navigation->admin_permission_id = $permission;
		$navigation->hide_no_children = $hideNoChildren;

		$masterPhrase = $navigation->getMasterPhrase();
		$masterPhrase->phrase_text = $title;

		$navigation->addCascadedSave($masterPhrase);
		$navigation->save();

		$action = $isUpdate ? 'updated' : 'created';
		$io->success("Admin navigation entry '$id' $action.");

		$io->table(
			['Property', 'Value'],
			[
				['Navigation ID', $id],
				['Title', $this->truncateText($title, 60)],
				['Parent', $parent ?: '(none)'],
				['Link', $link ?: '(none)'],
				['Icon', $icon ?: '(none)'],
				['Display Order', (string) $order],
				['Permission', $permission ?: '(none)'],
				['Hide No Children', $hideNoChildren ? 'Yes' : 'No'],
				['Add-on', $this->addOnId],
			]
		);

		return Command::SUCCESS;
	}

	protected function validateId(string $id, SymfonyStyle $io): bool
	{
		if (!preg_match('/^[a-z0-9_]+$/i', $id))
		{
			$io->error('Navigation ID may only contain letters, numbers, and underscores.');
			return false;
		}

		if (strlen($id) > 50)
		{
			$io->error('Navigation ID must be 50 characters or less.');
			return false;
		}

		return true;
	}

	protected function getExistingNavigation(string $id): ?AdminNavigation
	{
		return \XF::finder(AdminNavigationFinder::class)
			->where('navigation_id', $id)
			->fetchOne();
	}
}
