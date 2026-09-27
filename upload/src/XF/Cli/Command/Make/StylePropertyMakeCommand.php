<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\StyleProperty;
use XF\Finder\StylePropertyFinder;
use XF\Finder\StylePropertyGroupFinder;
use XF\Mvc\Entity\Entity;

use function in_array, is_array;

class StylePropertyMakeCommand extends AbstractDevelopmentOutputMakeCommand
{
	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:style-property')
			->setDescription('Create a style property definition')
			->addArgument('name', InputArgument::REQUIRED, 'Property name')
			->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Style property group')
			->addOption('group-title', null, InputOption::VALUE_REQUIRED, 'Create a missing group with this title')
			->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Property title')
			->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Property description', '')
			->addOption('type', null, InputOption::VALUE_REQUIRED, 'Property type: value or css', 'value')
			->addOption('value-type', null, InputOption::VALUE_REQUIRED, 'Value editor type', 'string')
			->addOption('value', null, InputOption::VALUE_REQUIRED, 'Default value', '')
			->addOption('css-components', null, InputOption::VALUE_REQUIRED, 'Comma-separated CSS components', '')
			->addOption('order', 'o', InputOption::VALUE_REQUIRED, 'Display order', '0')
			->addOption('variations', null, InputOption::VALUE_NONE, 'Enable style variations');
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->interactAddOn($input, $io, 'name');
		if (!$input->getOption('group'))
		{
			$input->setOption('group', $io->ask('Style property group'));
		}
		if ($input->getOption('title') === null)
		{
			$input->setOption('title', $io->ask('Property title', (string) $input->getArgument('name')));
		}
	}

	protected function getEntityClass(): string
	{
		return StyleProperty::class;
	}

	protected function getAddOnArgument(): ?string
	{
		return 'name';
	}

	protected function getEntityIdentifier(InputInterface $input): int
	{
		$existing = $this->findExistingEntity($input);
		return $existing ? (int) $existing->property_id : 0;
	}

	protected function findExistingEntity(InputInterface $input): ?Entity
	{
		return \XF::finder(StylePropertyFinder::class)
			->where('style_id', 0)
			->where('property_name', $input->getArgument('name'))
			->fetchOne();
	}

	protected function configureEntity(Entity $entity, InputInterface $input, OutputInterface $output, SymfonyStyle $io): bool
	{
		$group = (string) $input->getOption('group');
		if ($group === '')
		{
			$io->error('The --group option is required.');
			return false;
		}

		$type = (string) $input->getOption('type');
		if (!in_array($type, ['value', 'css'], true))
		{
			$io->error("Invalid property type '{$type}'.");
			return false;
		}

		$value = (string) $input->getOption('value');
		if ($type === 'css')
		{
			$decoded = json_decode($value, true);
			if (!is_array($decoded))
			{
				$io->error('CSS property values must be supplied as a JSON object.');
				return false;
			}
			$value = $decoded;
		}

		$existingGroup = \XF::finder(StylePropertyGroupFinder::class)->where([
			'group_name' => $group,
			'style_id' => 0,
		])->fetchOne();
		if (!$existingGroup)
		{
			$groupTitle = $input->getOption('group-title');
			if ($groupTitle === null)
			{
				$io->error("Style property group '{$group}' does not exist. Use --group-title to create it.");
				return false;
			}
			if (!$this->runMakeCommand('xf-make:style-property-group', [
				'id' => $group,
				'--addon' => $this->addOnId,
				'--title' => $groupTitle,
			], $output, $io))
			{
				return false;
			}
		}

		$entity->style_id = 0;
		$entity->property_name = $input->getArgument('name');
		$entity->group_name = $group;
		$entity->title = (string) ($input->getOption('title') ?? $entity->property_name);
		$entity->description = (string) $input->getOption('description');
		$entity->property_type = $type;
		$entity->value_type = $type === 'value' ? (string) $input->getOption('value-type') : '';
		$entity->property_value = $value;
		$entity->css_components = array_values(array_filter(array_map('trim', str_getcsv((string) $input->getOption('css-components')))));
		$entity->display_order = (int) $input->getOption('order');
		$entity->has_variations = $input->getOption('variations');
		return true;
	}

	protected function getSuccessDescription(Entity $entity): string
	{
		return "Style property '{$entity->property_name}'";
	}
}
