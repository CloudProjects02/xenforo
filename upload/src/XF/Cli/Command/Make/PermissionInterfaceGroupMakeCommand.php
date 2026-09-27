<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\PermissionInterfaceGroup;
use XF\Mvc\Entity\Entity;

class PermissionInterfaceGroupMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:permission-interface-group')
			->setDescription('Create a permission interface group')
			->addArgument('id', InputArgument::REQUIRED, 'Interface group ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Group title')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '1')
			->addOption('moderator', null, InputOption::VALUE_NONE, 'Mark as a moderator permission group');
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
		return PermissionInterfaceGroup::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->interface_group_id = $input->getArgument('id');
		$entity->display_order = (int) $input->getOption('order');
		$entity->is_moderator = $input->getOption('moderator');

		$title = $entity->getMasterPhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->interface_group_id);
		$entity->addCascadedSave($title);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Permission interface group '{$entity->interface_group_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Group ID', $entity->interface_group_id],
			['Display order', (string) $entity->display_order],
			['Moderator', $entity->is_moderator ? 'Yes' : 'No'],
		];
	}
}
