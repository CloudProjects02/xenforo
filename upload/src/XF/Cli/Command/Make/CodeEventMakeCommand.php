<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\CodeEvent;
use XF\Mvc\Entity\Entity;

use function count;

class CodeEventMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:code-event')
			->setDescription('Create a code event')
			->addArgument('id', InputArgument::REQUIRED, 'Code event ID')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Event description', '')
			->addOption('hint-description', null, InputOption::VALUE_REQUIRED, 'Listener hint description')
			->addOption(
				'argument',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Repeatable CSV callback argument: name,type,description'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$this->interactAddOn($input, new SymfonyStyle($input, $output), 'id');
	}

	protected function getEntityClass(): string
	{
		return CodeEvent::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$arguments = [];
		foreach ($input->getOption('argument') AS $definition)
		{
			$parts = str_getcsv($definition);
			$name = trim($parts[0] ?? '');
			if ($name === '' || count($parts) > 3)
			{
				$io->error("Invalid --argument '{$definition}'. Expected CSV: name,type,description");
				return false;
			}

			$arguments[] = [
				'name' => $name,
				'type' => trim($parts[1] ?? ''),
				'description' => trim($parts[2] ?? ''),
			];
		}

		$entity->event_id = $input->getArgument('id');
		$entity->description = (string) $input->getOption('description');
		$entity->hint_description = $input->getOption('hint-description');
		$entity->arguments = $arguments;
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Code event '{$entity->event_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Event ID', $entity->event_id],
			['Arguments', (string) count($entity->arguments ?? [])],
		];
	}
}
