<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\ContentTypeField;
use XF\Mvc\Entity\Entity;

class ContentTypeFieldMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:content-type-field')
			->setDescription('Create a content-type field')
			->addArgument('content-type', InputArgument::REQUIRED, 'Content type ID')
			->addArgument('field', InputArgument::REQUIRED, 'Field name')
			->addArgument('value', InputArgument::REQUIRED, 'Field value');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'content-type');
	}

	protected function getAddOnArgument(): ?string
	{
		return 'content-type';
	}

	protected function getEntityClass(): string
	{
		return ContentTypeField::class;
	}

	protected function getEntityIdentifier(InputInterface $input): array
	{
		return [
			'content_type' => $input->getArgument('content-type'),
			'field_name' => $input->getArgument('field'),
		];
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->content_type = $input->getArgument('content-type');
		$entity->field_name = $input->getArgument('field');
		$entity->field_value = $input->getArgument('value');
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Content-type field '{$entity->content_type}:{$entity->field_name}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Content type', $entity->content_type],
			['Field', $entity->field_name],
			['Value', $entity->field_value],
		];
	}
}
