<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\BbCodeMediaSite;
use XF\Mvc\Entity\Entity;

class BbCodeMediaSiteMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:bb-code-media-site')
			->setDescription('Create a BB code media site and its embed template')
			->addArgument('id', InputArgument::REQUIRED, 'Media site ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Site title')
			->addOption('site-url', null, InputOption::VALUE_REQUIRED, 'Site URL', '')
			->addOption('match-urls', null, InputOption::VALUE_REQUIRED, 'Line-separated URL match patterns', '')
			->addOption('match-regex', null, InputOption::VALUE_NONE, 'Treat match URLs as regular expressions')
			->addOption('embed-html', null, InputOption::VALUE_REQUIRED, 'Embed template content')
			->addOption('embed-html-file', null, InputOption::VALUE_REQUIRED, 'Read embed template content from a file')
			->addOption('cookie-third-parties', null, InputOption::VALUE_REQUIRED, 'Third parties setting cookies', '')
			->addOption('unsupported', null, InputOption::VALUE_NONE, 'Mark the site as unsupported')
			->addOption('inactive', null, InputOption::VALUE_NONE, 'Create the site as inactive');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Site title', (string) $input->getArgument('id')));
		}
		if ($input->getOption('embed-html') === null && $input->getOption('embed-html-file') === null)
		{
			$input->setOption('embed-html', $io->ask('Embed template content'));
		}
	}

	protected function getEntityClass(): string
	{
		return BbCodeMediaSite::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$embedHtml = $this->readTextOption($input, 'embed-html', 'embed-html-file', $io);
		if ($embedHtml === null || $embedHtml === '')
		{
			$io->error('Embed template content is required through --embed-html or --embed-html-file.');
			return false;
		}

		$entity->media_site_id = $input->getArgument('id');
		$entity->site_title = (string) ($input->getOption('title') ?? $entity->media_site_id);
		$entity->site_url = (string) $input->getOption('site-url');
		$entity->match_urls = (string) $input->getOption('match-urls');
		$entity->match_is_regex = $input->getOption('match-regex');
		$entity->cookie_third_parties = (string) $input->getOption('cookie-third-parties');
		$entity->supported = !$input->getOption('unsupported');
		$entity->active = !$input->getOption('inactive');

		$template = $entity->getMasterTemplate();
		$originalEmbedHtml = $template->template;
		if (!$template->set('template', $embedHtml))
		{
			$io->error($template->getErrors());
			return false;
		}
		if ($originalEmbedHtml !== $embedHtml)
		{
			$entity->setOption('requires_dev_output_update', true);
		}
		$entity->addCascadedSave($template);
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "BB code media site '{$entity->media_site_id}'";
	}
}
