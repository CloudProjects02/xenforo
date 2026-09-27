<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\WidgetPosition;
use XF\Mvc\Entity\Entity;

class WidgetPositionMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:widget-position')
			->setDescription('Create a widget position')
			->addArgument('id', InputArgument::REQUIRED, 'Widget position ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Position title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Position description', '')
			->addOption('inactive', null, InputOption::VALUE_NONE, 'Create the position as inactive');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Position title', (string) $input->getArgument('id')));
		}
	}

	protected function getEntityClass(): string
	{
		return WidgetPosition::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->position_id = $input->getArgument('id');
		$entity->active = !$input->getOption('inactive');

		$title = $entity->getMasterTitlePhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->position_id);
		$entity->addCascadedSave($title);

		$description = $entity->getMasterDescriptionPhrase();
		$description->phrase_text = (string) $input->getOption('description');
		$entity->addCascadedSave($description);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Widget position '{$entity->position_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Position ID', $entity->position_id],
			['Active', $entity->active ? 'Yes' : 'No'],
		];
	}
}
