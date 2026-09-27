<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Permission;
use XF\Entity\PermissionInterfaceGroup;
use XF\Mvc\Entity\Entity;

use function in_array;

class PermissionMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:permission')
			->setDescription('Create a user permission')
			->addArgument('group', InputArgument::REQUIRED, 'Permission group ID')
			->addArgument('id', InputArgument::REQUIRED, 'Permission ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Permission title')
			->addOption('type', null, InputOption::VALUE_REQUIRED, 'Permission type: flag or integer', 'flag')
			->addOption('interface-group', 'i', InputOption::VALUE_REQUIRED, 'Permission interface group ID')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '1')
			->addOption('depends-on', null, InputOption::VALUE_REQUIRED, 'Permission ID this permission depends on', '')
			->addOption('create-interface-group', null, InputOption::VALUE_NONE, 'Create the interface group if missing')
			->addOption('interface-group-title', null, InputOption::VALUE_REQUIRED, 'Title for a created interface group')
			->addOption('interface-group-order', null, InputOption::VALUE_REQUIRED, 'Order for a created interface group', '1')
			->addOption('interface-group-moderator', null, InputOption::VALUE_NONE, 'Create a moderator interface group');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'group');

		if (!$input->getOption('interface-group'))
		{
			$input->setOption('interface-group', $io->ask('Permission interface group ID'));
		}
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Permission title', (string) $input->getArgument('id')));
		}
	}

	protected function getAddOnArgument(): ?string
	{
		return 'group';
	}

	protected function getEntityClass(): string
	{
		return Permission::class;
	}

	protected function getEntityIdentifier(InputInterface $input): array
	{
		return [
			'permission_group_id' => $input->getArgument('group'),
			'permission_id' => $input->getArgument('id'),
		];
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$type = (string) $input->getOption('type');
		if (!in_array($type, ['flag', 'integer'], true))
		{
			$io->error("Invalid permission type '{$type}'. Expected flag or integer.");
			return false;
		}

		$interfaceGroupId = (string) $input->getOption('interface-group');
		if ($interfaceGroupId === '')
		{
			$io->error('The --interface-group option is required.');
			return false;
		}

		$interfaceGroup = \XF::em()->find(PermissionInterfaceGroup::class, $interfaceGroupId);
		if (!$interfaceGroup)
		{
			if (!$input->getOption('create-interface-group'))
			{
				$io->error("Permission interface group '{$interfaceGroupId}' does not exist. Use --create-interface-group to create it.");
				return false;
			}

			$arguments = [
				'id' => $interfaceGroupId,
				'--addon' => $this->addOnId,
				'--title' => $input->getOption('interface-group-title') ?: $interfaceGroupId,
				'--order' => $input->getOption('interface-group-order'),
			];
			if ($input->getOption('interface-group-moderator'))
			{
				$arguments['--moderator'] = true;
			}

			if (!$this->runMakeCommand('xf-make:permission-interface-group', $arguments, $output, $io))
			{
				return false;
			}
		}

		$entity->permission_group_id = $input->getArgument('group');
		$entity->permission_id = $input->getArgument('id');
		$entity->permission_type = $type;
		$entity->interface_group_id = $interfaceGroupId;
		$entity->display_order = (int) $input->getOption('order');
		$entity->depend_permission_id = (string) $input->getOption('depends-on');

		$title = $entity->getMasterPhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->permission_id);
		$entity->addCascadedSave($title);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Permission '{$entity->permission_group_id}:{$entity->permission_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Permission', "{$entity->permission_group_id}:{$entity->permission_id}"],
			['Type', $entity->permission_type],
			['Interface group', $entity->interface_group_id],
			['Depends on', $entity->depend_permission_id ?: '(none)'],
		];
	}
}
