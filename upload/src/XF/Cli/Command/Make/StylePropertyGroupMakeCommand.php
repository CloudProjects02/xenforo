<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\StylePropertyGroup;
use XF\Finder\StylePropertyGroupFinder;
use XF\Mvc\Entity\Entity;

class StylePropertyGroupMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:style-property-group')
			->setDescription('Create a master style-property group')
			->addArgument('id', InputArgument::REQUIRED, 'Style-property group name')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Group title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Group description', '')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '0');
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
		return StylePropertyGroup::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		$group = $this->findExistingEntity($input);
		return $group ? (string) $group->property_group_id : '0';
	}

	protected function findExistingEntity(InputInterface $input): ?Entity
	{
		return \XF::finder(StylePropertyGroupFinder::class)->where([
			'group_name' => $input->getArgument('id'),
			'style_id' => 0,
		])->fetchOne();
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->style_id = 0;
		$entity->group_name = $input->getArgument('id');
		$entity->title = (string) ($input->getOption('title') ?? $entity->group_name);
		$entity->description = (string) $input->getOption('description');
		$entity->display_order = (int) $input->getOption('order');
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Style-property group '{$entity->group_name}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Group name', $entity->group_name],
			['Title', $entity->title],
			['Display order', (string) $entity->display_order],
		];
	}
}
