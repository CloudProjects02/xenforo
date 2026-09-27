<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\AdvertisingPosition;
use XF\Mvc\Entity\Entity;

use function count, in_array;

class AdvertisingPositionMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:advertising-position')
			->setDescription('Create an advertising position')
			->addArgument('id', InputArgument::REQUIRED, 'Advertising position ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Position title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Position description', '')
			->addOption(
				'argument',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Repeatable CSV argument definition: name,required'
			)
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
		return AdvertisingPosition::class;
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
			if ($name === '' || count($parts) > 2)
			{
				$io->error("Invalid --argument '{$definition}'. Expected CSV: name,required");
				return false;
			}

			$required = strtolower(trim($parts[1] ?? 'false'));
			if (!in_array($required, ['1', '0', 'true', 'false', 'yes', 'no'], true))
			{
				$io->error("Invalid required value in --argument '{$definition}'.");
				return false;
			}

			$arguments[] = [
				'argument' => $name,
				'required' => in_array($required, ['1', 'true', 'yes'], true),
			];
		}

		$entity->position_id = $input->getArgument('id');
		$entity->arguments = $arguments;
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
		return "Advertising position '{$entity->position_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Position ID', $entity->position_id],
			['Arguments', (string) count($entity->arguments)],
			['Active', $entity->active ? 'Yes' : 'No'],
		];
	}
}
