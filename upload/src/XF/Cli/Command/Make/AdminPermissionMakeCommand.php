<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\AdminPermission;
use XF\Mvc\Entity\Entity;

class AdminPermissionMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:admin-permission')
			->setDescription('Create an admin permission')
			->addArgument('id', InputArgument::REQUIRED, 'Admin permission ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Permission title')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '0');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Permission title', (string) $input->getArgument('id')));
		}
	}

	protected function getEntityClass(): string
	{
		return AdminPermission::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->admin_permission_id = $input->getArgument('id');
		$entity->display_order = (int) $input->getOption('order');

		$title = $entity->getMasterPhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->admin_permission_id);
		$entity->addCascadedSave($title);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Admin permission '{$entity->admin_permission_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Permission ID', $entity->admin_permission_id],
			['Display order', (string) $entity->display_order],
		];
	}
}
