<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\TemplateModification;
use XF\Finder\TemplateModificationFinder;
use XF\Mvc\Entity\Entity;

use function in_array;

class TemplateModificationMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:template-modification')
			->setDescription('Create a template modification')
			->addArgument('key', InputArgument::REQUIRED, 'Modification key')
			->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Template type: public, admin, or email', 'public')
			->addOption('template', null, InputOption::VALUE_REQUIRED, 'Target template title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Modification description', '')
			->addOption('action', null, InputOption::VALUE_REQUIRED, 'Action: str_replace, preg_replace, or callback', 'str_replace')
			->addOption('find', null, InputOption::VALUE_REQUIRED, 'Find expression')
			->addOption('find-file', null, InputOption::VALUE_REQUIRED, 'Read the find expression from a file')
			->addOption('replace', null, InputOption::VALUE_REQUIRED, 'Replacement text or callback')
			->addOption('replace-file', null, InputOption::VALUE_REQUIRED, 'Read replacement text from a file')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Execution order', '10')
			->addOption('disabled', null, InputOption::VALUE_NONE, 'Create the modification as disabled');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'key', false);
		if (!$input->getOption('template'))
		{
			$input->setOption('template', $io->ask('Target template'));
		}
		if ($input->getOption('find') === null && $input->getOption('find-file') === null)
		{
			$input->setOption('find', $io->ask('Find expression'));
		}
	}

	protected function getAddOnArgument(): ?string
	{
		return 'key';
	}

	protected function getEntityClass(): string
	{
		return TemplateModification::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		$existing = $this->findExistingEntity($input);
		return $existing ? (string) $existing->modification_id : '0';
	}

	protected function findExistingEntity(InputInterface $input): ?Entity
	{
		return \XF::finder(TemplateModificationFinder::class)
			->where('modification_key', $input->getArgument('key'))
			->fetchOne();
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$type = (string) $input->getOption('type');
		$action = (string) $input->getOption('action');
		if (!in_array($type, ['public', 'admin', 'email'], true))
		{
			$io->error("Invalid template type '{$type}'.");
			return false;
		}
		if (!in_array($action, ['str_replace', 'preg_replace', 'callback'], true))
		{
			$io->error("Invalid modification action '{$action}'.");
			return false;
		}

		$template = (string) $input->getOption('template');
		if ($template === '')
		{
			$io->error('The --template option is required.');
			return false;
		}

		$find = $this->readTextOption($input, 'find', 'find-file', $io);
		if ($find === null)
		{
			$io->error('A find expression is required through --find or --find-file.');
			return false;
		}
		if ($input->getOption('replace') === null && $input->getOption('replace-file') === null)
		{
			$replace = '';
		}
		else
		{
			$replace = $this->readTextOption($input, 'replace', 'replace-file', $io);
			if ($replace === null)
			{
				return false;
			}
		}

		$entity->type = $type;
		$entity->template = $template;
		$entity->modification_key = $input->getArgument('key');
		$entity->description = (string) $input->getOption('description');
		$entity->action = $action;
		$entity->find = $find;
		$entity->replace = $replace;
		$entity->execution_order = (int) $input->getOption('order');
		$entity->enabled = !$input->getOption('disabled');
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Template modification '{$entity->modification_key}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Key', $entity->modification_key],
			['Type', $entity->type],
			['Template', $entity->template],
			['Action', $entity->action],
			['Enabled', $entity->enabled ? 'Yes' : 'No'],
		];
	}
}
