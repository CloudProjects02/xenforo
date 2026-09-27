<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\MemberStat;
use XF\Finder\MemberStatFinder;
use XF\Mvc\Entity\Entity;

use function in_array;

class MemberStatMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:member-stat')
			->setDescription('Create a member statistic')
			->addArgument('key', InputArgument::REQUIRED, 'Member statistic key')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Statistic title')
			->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort field', 'message_count')
			->addOption('direction', null, InputOption::VALUE_REQUIRED, 'Sort direction: asc or desc', 'desc')
			->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'User limit', '20')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '10')
			->addOption('cache-lifetime', null, InputOption::VALUE_REQUIRED, 'Cache lifetime in minutes', '60')
			->addOption('hide-value', null, InputOption::VALUE_NONE, 'Hide the statistic value')
			->addOption('hide-overview', null, InputOption::VALUE_NONE, 'Hide from the member overview')
			->addOption('inactive', null, InputOption::VALUE_NONE, 'Create the statistic as inactive');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'key');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Statistic title', (string) $input->getArgument('key')));
		}
	}

	protected function getEntityClass(): string
	{
		return MemberStat::class;
	}

	protected function getAddOnArgument(): ?string
	{
		return 'key';
	}

	protected function getEntityIdentifier(InputInterface $input): int
	{
		$existing = $this->findExistingEntity($input);
		return $existing ? (int) $existing->member_stat_id : 0;
	}

	protected function findExistingEntity(InputInterface $input): ?Entity
	{
		return \XF::finder(MemberStatFinder::class)
			->where('member_stat_key', $input->getArgument('key'))
			->fetchOne();
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$direction = (string) $input->getOption('direction');
		if (!in_array($direction, ['asc', 'desc'], true))
		{
			$io->error("Invalid sort direction '{$direction}'.");
			return false;
		}

		$entity->member_stat_key = $input->getArgument('key');
		$entity->sort_order = (string) $input->getOption('sort');
		$entity->sort_direction = $direction;
		$entity->show_value = !$input->getOption('hide-value');
		$entity->overview_display = !$input->getOption('hide-overview');
		$entity->active = !$input->getOption('inactive');
		$entity->user_limit = (int) $input->getOption('limit');
		$entity->display_order = (int) $input->getOption('order');
		$entity->cache_lifetime = (int) $input->getOption('cache-lifetime');

		$title = $entity->getMasterPhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->member_stat_key);
		$entity->addCascadedSave($title);
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Member statistic '{$entity->member_stat_key}'";
	}
}
