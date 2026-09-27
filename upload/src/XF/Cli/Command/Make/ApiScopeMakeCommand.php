<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\ApiScope;
use XF\Mvc\Entity\Entity;

class ApiScopeMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:api-scope')
			->setDescription('Create an API scope')
			->addArgument('id', InputArgument::REQUIRED, 'API scope ID, such as resource:read')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Scope description', '')
			->addOption('no-oauth', null, InputOption::VALUE_NONE, 'Disallow use with OAuth clients');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		if (!$input->getOption('addon'))
		{
			$input->setOption('addon', $this->promptForAddOn($io));
		}
	}

	protected function getAddOnArgument(): ?string
	{
		return null;
	}

	protected function getEntityClass(): string
	{
		return ApiScope::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$entity->api_scope_id = $input->getArgument('id');
		$entity->usable_with_oauth_clients = !$input->getOption('no-oauth');

		$description = $entity->getMasterPhrase();
		$description->phrase_text = (string) $input->getOption('description');
		$entity->addCascadedSave($description);

		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "API scope '{$entity->api_scope_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Scope ID', $entity->api_scope_id],
			['OAuth clients', $entity->usable_with_oauth_clients ? 'Allowed' : 'Disallowed'],
		];
	}
}
