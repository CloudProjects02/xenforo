<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\HelpPage;
use XF\Mvc\Entity\Entity;

class HelpPageMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:help-page')
			->setDescription('Create a help page and its phrases and template')
			->addArgument('id', InputArgument::REQUIRED, 'Help page ID')
			->addOption('page-name', null, InputOption::VALUE_REQUIRED, 'URL portion')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Page title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Page description', '')
			->addOption('content', null, InputOption::VALUE_REQUIRED, 'Template content')
			->addOption('content-file', null, InputOption::VALUE_REQUIRED, 'Read template content from a file')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '1')
			->addOption('advanced', null, InputOption::VALUE_NONE, 'Enable advanced mode')
			->addOption('inactive', null, InputOption::VALUE_NONE, 'Create the page as inactive');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Page title', (string) $input->getArgument('id')));
		}
		if ($input->getOption('content') === null && $input->getOption('content-file') === null)
		{
			$input->setOption('content', $io->ask('Template content'));
		}
	}

	protected function getEntityClass(): string
	{
		return HelpPage::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$content = $this->readTextOption($input, 'content', 'content-file', $io);
		if ($content === null || $content === '')
		{
			$io->error('Template content is required through --content or --content-file.');
			return false;
		}

		$entity->page_id = $input->getArgument('id');
		$entity->page_name = (string) ($input->getOption('page-name') ?? str_replace('_', '-', $entity->page_id));
		$entity->display_order = (int) $input->getOption('order');
		$entity->advanced_mode = $input->getOption('advanced');
		$entity->active = !$input->getOption('inactive');

		$title = $entity->getMasterPhrase(true);
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->page_id);
		$entity->addCascadedSave($title);

		$description = $entity->getMasterPhrase(false);
		$description->phrase_text = (string) $input->getOption('description');
		$entity->addCascadedSave($description);

		$template = $entity->getMasterTemplate();
		if (!$template->set('template', $content))
		{
			$io->error($template->getErrors());
			return false;
		}
		$entity->addCascadedSave($template);
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Help page '{$entity->page_id}'";
	}
}
