<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Navigation;
use XF\Mvc\Entity\Entity;

use function count;

class NavigationMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:navigation')
			->setDescription('Create a public navigation entry')
			->addArgument('id', InputArgument::REQUIRED, 'Navigation ID')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Navigation title')
			->addOption('parent', 'p', InputOption::VALUE_REQUIRED, 'Parent navigation ID', '')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '1')
			->addOption('type', null, InputOption::VALUE_REQUIRED, 'Navigation type: basic, callback, or node', 'basic')
			->addOption('link', 'l', InputOption::VALUE_REQUIRED, 'Basic navigation link', '')
			->addOption('display-condition', null, InputOption::VALUE_REQUIRED, 'Basic navigation display condition', '')
			->addOption('callback', null, InputOption::VALUE_REQUIRED, 'Callback navigation Class::method')
			->addOption('context', null, InputOption::VALUE_REQUIRED, 'Callback navigation context', '')
			->addOption('node-id', null, InputOption::VALUE_REQUIRED, 'Node ID for node navigation')
			->addOption('with-children', null, InputOption::VALUE_NONE, 'Include child nodes')
			->addOption('node-title', null, InputOption::VALUE_NONE, 'Use the node title')
			->addOption(
				'attribute',
				null,
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Repeatable CSV HTML attribute: name,value'
			)
			->addOption('disabled', null, InputOption::VALUE_NONE, 'Create the entry as disabled');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'id');
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Navigation title', (string) $input->getArgument('id')));
		}
	}

	protected function getEntityClass(): string
	{
		return Navigation::class;
	}

	protected function getEntityIdentifier(InputInterface $input): string
	{
		return (string) $input->getArgument('id');
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$parentId = (string) $input->getOption('parent');
		if ($parentId !== '' && !\XF::em()->find(Navigation::class, $parentId))
		{
			$io->error("Parent navigation entry '{$parentId}' does not exist.");
			return false;
		}

		$extraNames = [];
		$extraValues = [];
		foreach ($input->getOption('attribute') AS $attribute)
		{
			$parts = str_getcsv($attribute);
			if (count($parts) !== 2 || trim($parts[0]) === '')
			{
				$io->error("Invalid --attribute '{$attribute}'. Expected CSV: name,value");
				return false;
			}
			$extraNames[] = trim($parts[0]);
			$extraValues[] = $parts[1];
		}

		$type = (string) $input->getOption('type');
		switch ($type)
		{
			case 'basic':
				$config = [
					'link' => (string) $input->getOption('link'),
					'display_condition' => (string) $input->getOption('display-condition'),
					'extra_attr_names' => $extraNames,
					'extra_attr_values' => $extraValues,
				];
				break;

			case 'callback':
				$callback = explode('::', (string) $input->getOption('callback'), 2);
				if (count($callback) !== 2)
				{
					$io->error('Callback navigation requires --callback=Class::method.');
					return false;
				}
				$config = [
					'callback_class' => $callback[0],
					'callback_method' => $callback[1],
					'context' => (string) $input->getOption('context'),
				];
				break;

			case 'node':
				if ($input->getOption('node-id') === null)
				{
					$io->error('Node navigation requires --node-id.');
					return false;
				}
				$config = [
					'node_id' => (int) $input->getOption('node-id'),
					'with_children' => $input->getOption('with-children'),
					'node_title' => $input->getOption('node-title'),
					'extra_attr_names' => $extraNames,
					'extra_attr_values' => $extraValues,
				];
				break;

			default:
				$io->error("Unknown navigation type '{$type}'.");
				return false;
		}

		$entity->navigation_id = $input->getArgument('id');
		$entity->parent_navigation_id = $parentId;
		$entity->display_order = (int) $input->getOption('order');
		$entity->enabled = !$input->getOption('disabled');
		if (!$entity->setTypeFromInput($type, $config))
		{
			foreach ($entity->getErrors() AS $error)
			{
				$io->error((string) $error);
			}
			return false;
		}

		$title = $entity->getMasterPhrase();
		$title->phrase_text = (string) ($input->getOption('title') ?? $entity->navigation_id);
		$entity->addCascadedSave($title);
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Navigation entry '{$entity->navigation_id}'";
	}

	protected function getSummaryRows(Entity $entity): array
	{
		return [
			['Navigation ID', $entity->navigation_id],
			['Type', $entity->navigation_type_id],
			['Parent', $entity->parent_navigation_id ?: '(none)'],
			['Active', $entity->enabled ? 'Yes' : 'No'],
		];
	}
}
