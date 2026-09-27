<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\BbCode;
use XF\Mvc\Entity\Entity;

use function in_array;

class BbCodeMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:bb-code')
			->setDescription('Create a custom BB code')
			->addArgument('id', InputArgument::REQUIRED, 'BB code tag')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'BB code title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'BB code description', '')
			->addOption('example', null, InputOption::VALUE_REQUIRED, 'Example input', '')
			->addOption('output', null, InputOption::VALUE_REQUIRED, 'Example output', '')
			->addOption('mode', 'm', InputOption::VALUE_REQUIRED, 'Mode: replace or callback', 'replace')
			->addOption('has-option', null, InputOption::VALUE_REQUIRED, 'Option mode: yes, no, or optional', 'no')
			->addOption('replace-html', null, InputOption::VALUE_REQUIRED, 'HTML replacement')
			->addOption('replace-html-file', null, InputOption::VALUE_REQUIRED, 'Read HTML replacement from a file')
			->addOption('replace-text', null, InputOption::VALUE_REQUIRED, 'Text replacement', '')
			->addOption('callback-class', null, InputOption::VALUE_REQUIRED, 'Callback class', '')
			->addOption('callback-method', null, InputOption::VALUE_REQUIRED, 'Callback method', '')
			->addOption('inactive', null, InputOption::VALUE_NONE, 'Create the BB code as inactive');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('BB code title', strtoupper((string) $input->getArgument('id'))));
		}
	}

	protected function getEntityClass(): string
	{
		return BbCode::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return strtolower((string) $input->getArgument('id'));
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$mode = (string) $input->getOption('mode');
		$hasOption = (string) $input->getOption('has-option');
		if (!in_array($mode, ['replace', 'callback'], true) || !in_array($hasOption, ['yes', 'no', 'optional'], true))
		{
			$io->error('Invalid --mode or --has-option value.');
			return false;
		}

		if ($input->getOption('replace-html') === null && $input->getOption('replace-html-file') === null)
		{
			$replaceHtml = '';
		}
		else
		{
			$replaceHtml = $this->readTextOption($input, 'replace-html', 'replace-html-file', $io);
			if ($replaceHtml === null)
			{
				return false;
			}
		}

		$entity->bb_code_id = strtolower((string) $input->getArgument('id'));
		$entity->bb_code_mode = $mode;
		$entity->has_option = $hasOption;
		$entity->replace_html = $replaceHtml;
		$entity->replace_text = (string) $input->getOption('replace-text');
		$entity->callback_class = (string) $input->getOption('callback-class');
		$entity->callback_method = (string) $input->getOption('callback-method');
		$entity->active = !$input->getOption('inactive');

		foreach (['title', 'description', 'example', 'output'] AS $type)
		{
			$phraseType = $type === 'description' ? 'desc' : $type;
			$phrase = $entity->getMasterPhrase($phraseType);
			$phrase->phrase_text = (string) ($input->getOption($type) ?? $entity->bb_code_id);
			$entity->addCascadedSave($phrase);
		}
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "BB code '{$entity->bb_code_id}'";
	}
}
