<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Mvc\Entity\Entity;

abstract class AbstractDevelopmentOutputMakeCommand extends AbstractMakeCommand
{
	abstract protected function getEntityClass(): string;

	abstract protected function getEntityIdentifier(InputInterface $input);

	abstract protected function configureEntity(
		Entity $entity,
		InputInterface $input,
		OutputInterface $output,
		SymfonyStyle $io
	): bool;

	protected function getAddOnArgument(): ?string
	{
		return 'id';
	}

	protected function allowXF(): bool
	{
		return true;
	}

	protected function findExistingEntity(InputInterface $input): ?Entity
	{
		return \XF::em()->find($this->getEntityClass(), $this->getEntityIdentifier($input));
	}

	protected function createEntity(InputInterface $input): Entity
	{
		return \XF::em()->create($this->getEntityClass());
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return $this->getName();
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [];
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);

		$addOnArgument = $this->getAddOnArgument();
		if ($addOnArgument !== null)
		{
			$this->resolveAddOnFromArgument($input, $addOnArgument);
		}

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io, $this->allowXF()))
		{
			return Command::FAILURE;
		}

		$entity = $this->findExistingEntity($input);
		$isUpdate = $entity !== null;

		if ($entity)
		{
			if (!$this->validateEntityOwner($entity, $io))
			{
				return Command::FAILURE;
			}

			if (!$input->getOption('force'))
			{
				$io->error('The target already exists. Use --force to replace its configured values.');
				return Command::FAILURE;
			}
		}
		else
		{
			$entity = $this->createEntity($input);
		}

		$entity->set('addon_id', $this->addOnId);
		if (!$this->configureEntity($entity, $input, $output, $io))
		{
			return Command::FAILURE;
		}

		try
		{
			$entity->save();
		}
		catch (\Throwable $e)
		{
			$io->error($e->getMessage());
			return Command::FAILURE;
		}

		$action = $isUpdate ? 'updated' : 'created';
		$io->success($this->getSuccessDescription($entity) . " {$action}.");

		$rows = $this->getSummaryRows($entity);
		if ($rows)
		{
			$rows[] = ['Add-on', $this->addOnId];
			$io->table(['Property', 'Value'], $rows);
		}

		return Command::SUCCESS;
	}
}
