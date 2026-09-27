<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\WidgetDefinition;
use XF\Mvc\Entity\Entity;

class WidgetDefinitionMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:widget-definition')
			->setDescription('Create a widget definition')
			->addArgument('id', InputArgument::REQUIRED, 'Definition ID')
			->addOption('class', 'c', InputOption::VALUE_REQUIRED, 'Widget class')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Definition title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Definition description', '');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if (!$input->getOption('class'))
		{
			$input->setOption('class', $io->ask('Widget class'));
		}
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Definition title', (string) $input->getArgument('id')));
		}
	}

	protected function getEntityClass(): string
	{
		return WidgetDefinition::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$class = (string) $input->getOption('class');
		if ($class === '')
		{
			$io->error('The --class option is required.');
			return false;
		}

		$entity->definition_id = $input->getArgument('id');
		$entity->definition_class = $class;

		$title = $entity->getMasterTitlePhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->definition_id);
		$entity->addCascadedSave($title);

		$description = $entity->getMasterDescriptionPhrase();
		$description->phrase_text = (string) $input->getOption('description');
		$entity->addCascadedSave($description);
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Widget definition '{$entity->definition_id}'";
	}
}
