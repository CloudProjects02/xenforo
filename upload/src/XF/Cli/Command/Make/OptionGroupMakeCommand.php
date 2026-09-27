<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\OptionGroup;
use XF\Mvc\Entity\Entity;

class OptionGroupMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:option-group')
			->setDescription('Create an option group')
			->addArgument('id', InputArgument::REQUIRED, 'Option group ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Group title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Group description', '')
			->addOption('icon', 'i', InputOption::VALUE_REQUIRED, 'Font Awesome 5 Pro icon class', '')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '1')
			->addOption('advanced', null, InputOption::VALUE_NONE, 'Mark the group as advanced')
			->addOption('debug-only', null, InputOption::VALUE_NONE, 'Show the group only in debug mode');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Group title', (string) $input->getArgument('id')));
		}
	}

	protected function getEntityClass(): string
	{
		return OptionGroup::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->group_id = $input->getArgument('id');
		$entity->icon = (string) $input->getOption('icon');
		$entity->display_order = (int) $input->getOption('order');
		$entity->advanced = $input->getOption('advanced');
		$entity->debug_only = $input->getOption('debug-only');

		$title = $entity->getMasterPhrase(true);
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->group_id);
		$entity->addCascadedSave($title);

		$description = $entity->getMasterPhrase(false);
		$description->phrase_text = (string) $input->getOption('description');
		$entity->addCascadedSave($description);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Option group '{$entity->group_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Group ID', $entity->group_id],
			['Icon', $entity->icon ?: '(none)'],
			['Display order', (string) $entity->display_order],
			['Advanced', $entity->advanced ? 'Yes' : 'No'],
			['Debug only', $entity->debug_only ? 'Yes' : 'No'],
		];
	}
}
