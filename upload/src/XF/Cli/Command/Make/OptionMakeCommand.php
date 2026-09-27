<?php

declare(strict_types=1);

namespace XF\Cli\Command\Make;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use XF\Entity\Option;
use XF\Entity\OptionGroup;
use XF\Finder\OptionFinder;
use XF\Finder\OptionGroupFinder;

use function count, in_array, strlen;

class OptionMakeCommand extends AbstractMakeCommand
{
	protected const DATA_TYPES = [
		'string',
		'integer',
		'numeric',
		'boolean',
		'array',
		'positive_integer',
		'unsigned_integer',
		'unsigned_numeric',
	];

	protected const EDIT_FORMATS = [
		'textbox',
		'textarea',
		'spinbox',
		'onoff',
		'onofftextbox',
		'radio',
		'select',
		'checkbox',
		'template',
		'callback',
		'username',
	];

	protected const TYPE_FORMAT_MAP = [
		'boolean' => 'onoff',
		'integer' => 'spinbox',
		'positive_integer' => 'spinbox',
		'unsigned_integer' => 'spinbox',
		'numeric' => 'spinbox',
		'unsigned_numeric' => 'spinbox',
		'string' => 'textbox',
		'array' => 'checkbox',
	];

	protected const TYPE_DEFAULT_MAP = [
		'boolean' => '0',
		'integer' => '0',
		'positive_integer' => '1',
		'unsigned_integer' => '0',
		'numeric' => '0',
		'unsigned_numeric' => '0',
		'string' => '',
		'array' => '[]',
	];

	protected function configure(): void
	{
		parent::configure();

		$this
			->setName('xf-make:option')
			->setDescription('Create an option and register it with an option group')
			->addArgument(
				'id',
				InputArgument::REQUIRED,
				'The option ID (e.g. "myFeatureEnabled" or "Demo/Blog:featureEnabled")'
			)
			->addOption(
				'title',
				't',
				InputOption::VALUE_REQUIRED,
				'Option title text (default: humanized from ID)'
			)
			->addOption(
				'explain',
				'e',
				InputOption::VALUE_REQUIRED,
				'Option explanation/description text',
				''
			)
			->addOption(
				'type',
				null,
				InputOption::VALUE_REQUIRED,
				'Data type: string, integer, boolean, array, positive_integer, unsigned_integer, numeric, unsigned_numeric',
				'string'
			)
			->addOption(
				'format',
				null,
				InputOption::VALUE_REQUIRED,
				'Edit format: textbox, textarea, spinbox, onoff, radio, select, checkbox, template, callback, username'
			)
			->addOption(
				'format-params',
				null,
				InputOption::VALUE_REQUIRED,
				'Edit format parameters (e.g. key=value pairs for select, or template name)',
				''
			)
			->addOption(
				'default',
				'd',
				InputOption::VALUE_REQUIRED,
				'Default value (type-appropriate)'
			)
			->addOption(
				'group',
				'g',
				InputOption::VALUE_REQUIRED,
				'Option group ID to add this option to'
			)
			->addOption(
				'group-order',
				null,
				InputOption::VALUE_REQUIRED,
				'Display order within the group',
				'100'
			)
			->addOption(
				'group-title',
				null,
				InputOption::VALUE_REQUIRED,
				'Create a missing option group with this title'
			)
			->addOption(
				'validation',
				null,
				InputOption::VALUE_REQUIRED,
				'Validation callback (Class::method format)'
			)
			->addOption(
				'sub-options',
				null,
				InputOption::VALUE_REQUIRED,
				'Sub-options for array type (comma-separated, or * for any)'
			)
			->addOption(
				'advanced',
				null,
				InputOption::VALUE_NONE,
				'Mark as an advanced option'
			);
	}

	protected function interact(InputInterface $input, OutputInterface $output): void
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'id');

		$this->interactAddOn($input, $io, 'id', false);

		if (!$input->getArgument('id'))
		{
			$optionId = $io->ask(
				'Enter the option ID (e.g. myFeatureEnabled)',
				null,
				function ($value)
				{
					if (empty($value))
					{
						throw new \InvalidArgumentException('Option ID cannot be empty.');
					}
					return $value;
				}
			);
			$input->setArgument('id', $optionId);
		}

		$dataType = $input->getOption('type');
		if (!in_array($dataType, self::DATA_TYPES, true))
		{
			$dataType = $io->choice(
				'What data type should this option store?',
				[
					'string' => 'String - Text value',
					'integer' => 'Integer - Whole number (can be negative)',
					'positive_integer' => 'Positive Integer - Whole number >= 1',
					'unsigned_integer' => 'Unsigned Integer - Whole number >= 0',
					'boolean' => 'Boolean - On/off toggle',
					'array' => 'Array - Multiple values',
					'numeric' => 'Numeric - Decimal number (can be negative)',
					'unsigned_numeric' => 'Unsigned Numeric - Decimal number >= 0',
				],
				'string'
			);
			$input->setOption('type', $dataType);
		}

		$editFormat = $input->getOption('format');
		if (!$editFormat)
		{
			$suggestedFormat = self::TYPE_FORMAT_MAP[$dataType] ?? 'textbox';
			$editFormat = $io->choice(
				'How should this option be edited?',
				$this->getEditFormatChoices($dataType),
				$suggestedFormat
			);
			$input->setOption('format', $editFormat);
		}

		if ($editFormat === 'template' && !$input->getOption('format-params'))
		{
			$templateName = $io->ask(
				'Enter the template name (without admin: prefix)',
				'option_template_' . $input->getArgument('id')
			);
			$input->setOption('format-params', $templateName);
		}

		if ($editFormat === 'callback' && !$input->getOption('format-params'))
		{
			$callback = $io->ask(
				'Enter the callback (Class::method format)',
				null,
				function ($value)
				{
					if (empty($value) || strpos($value, '::') === false)
					{
						throw new \InvalidArgumentException('Callback must be in Class::method format.');
					}
					return $value;
				}
			);
			$input->setOption('format-params', $callback);
		}

		if (in_array($editFormat, ['radio', 'select', 'checkbox']) && !$input->getOption('format-params'))
		{
			$params = $io->ask(
				'Enter the options (format: value1=Label 1\nvalue2=Label 2, or leave empty for dynamic)'
			);
			$input->setOption('format-params', $params ?? '');
		}

		if ($dataType === 'array' && !$input->getOption('sub-options'))
		{
			$subOptions = $io->ask(
				'Enter sub-options (comma-separated keys, or * to allow any)',
				'*'
			);
			$input->setOption('sub-options', $subOptions);
		}

		if (!$input->getOption('group'))
		{
			$groups = $this->getAvailableGroups();
			if (empty($groups))
			{
				$io->warning('No option groups found. You must create an option group first using the Admin CP.');
				return;
			}

			$groupId = $io->choice(
				'Which option group should this belong to?',
				$groups
			);
			$input->setOption('group', $groupId);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$this->resolveAddOnFromArgument($input, 'id');

		$addOnId = $input->getOption('addon');
		if (!$addOnId)
		{
			$io->error('The --addon option is required.');
			return Command::FAILURE;
		}

		if (!$this->validateAddOn($addOnId, $io))
		{
			return Command::FAILURE;
		}

		$optionId = $input->getArgument('id');

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $optionId))
		{
			$io->error('Option ID must be alphanumeric (starting with a letter or underscore).');
			return Command::FAILURE;
		}

		if (strlen($optionId) > 50)
		{
			$io->error('Option ID must be 50 characters or less.');
			return Command::FAILURE;
		}

		$existingOption = $this->getExistingOption($optionId);
		if ($existingOption && !$this->validateEntityOwner($existingOption, $io))
		{
			return Command::FAILURE;
		}
		if ($existingOption && !$input->getOption('force'))
		{
			$io->error("Option '$optionId' already exists.");
			$io->note('Use --force to update the existing option.');
			return Command::FAILURE;
		}

		$dataType = $input->getOption('type');
		if (!in_array($dataType, self::DATA_TYPES, true))
		{
			$io->error("Invalid data type '$dataType'. Valid types: " . implode(', ', self::DATA_TYPES));
			return Command::FAILURE;
		}

		$editFormat = $input->getOption('format') ?: (self::TYPE_FORMAT_MAP[$dataType] ?? 'textbox');
		if (!in_array($editFormat, self::EDIT_FORMATS, true))
		{
			$io->error("Invalid edit format '$editFormat'. Valid formats: " . implode(', ', self::EDIT_FORMATS));
			return Command::FAILURE;
		}

		if (!$this->validateDataTypeEditFormat($dataType, $editFormat, $io))
		{
			return Command::FAILURE;
		}

		$groupId = $input->getOption('group');
		if (!$groupId)
		{
			$io->error('The --group option is required. Specify an existing option group ID.');
			return Command::FAILURE;
		}

		$group = $this->getOptionGroup($groupId);
		if (!$group)
		{
			$groupTitle = $input->getOption('group-title');
			if ($groupTitle === null)
			{
				$io->error("Option group '$groupId' does not exist. Use --group-title to create it.");
				return Command::FAILURE;
			}

			if (!$this->runMakeCommand('xf-make:option-group', [
				'id' => $groupId,
				'--addon' => $this->addOnId,
				'--title' => $groupTitle,
			], $output, $io))
			{
				return Command::FAILURE;
			}
			$group = $this->getOptionGroup($groupId);
			if (!$group)
			{
				$io->error("Option group '$groupId' could not be loaded after it was created.");
				return Command::FAILURE;
			}
		}

		$title = $input->getOption('title') ?: $this->humanizeIdentifier($optionId);
		$explain = $input->getOption('explain') ?? '';
		$formatParams = $input->getOption('format-params') ?? '';
		$defaultValue = $input->getOption('default') ?? (self::TYPE_DEFAULT_MAP[$dataType] ?? '');
		$groupOrder = (int) $input->getOption('group-order');
		$advanced = $input->getOption('advanced');

		$subOptions = [];
		if ($dataType === 'array')
		{
			$subOptionsInput = $input->getOption('sub-options') ?? '*';
			if ($subOptionsInput === '*')
			{
				$subOptions = ['*'];
			}
			else
			{
				$subOptions = array_map('trim', explode(',', $subOptionsInput));
			}
		}

		$validationClass = '';
		$validationMethod = '';
		$validation = $input->getOption('validation');
		if ($validation)
		{
			$parts = explode('::', $validation);
			if (count($parts) === 2)
			{
				$validationClass = $parts[0];
				$validationMethod = $parts[1];
			}
			else
			{
				$io->error('Validation callback must be in Class::method format.');
				return Command::FAILURE;
			}
		}

		if ($editFormat === 'template')
		{
			if (!$this->createTemplateIfNeeded($output, $formatParams, $io))
			{
				return Command::FAILURE;
			}
		}

		$db = \XF::db();
		$db->beginTransaction();

		try
		{
			if ($existingOption)
			{
				$option = $existingOption;
				$io->note("Updating existing option '$optionId'");
			}
			else
			{
				$option = \XF::em()->create(Option::class);
			}

			$option->option_id = $optionId;
			$option->edit_format = $editFormat;
			$option->edit_format_params = $formatParams;
			$option->data_type = $dataType;
			$option->sub_options = $subOptions;
			$option->validation_class = $validationClass;
			$option->validation_method = $validationMethod;
			$option->advanced = $advanced;
			$option->addon_id = $this->addOnId;

			if ($dataType === 'array' && $defaultValue === '[]')
			{
				$option->default_value = '[]';
			}
			else
			{
				$option->default_value = $defaultValue;
			}

			$option->save();

			$titlePhrase = $option->getMasterPhrase(true);
			$titlePhrase->phrase_text = $title;
			$titlePhrase->save();

			$explainPhrase = $option->getMasterPhrase(false);
			$explainPhrase->phrase_text = $explain;
			$explainPhrase->save();

			$option->updateRelations([$groupId => $groupOrder]);

			$db->commit();
		}
		catch (\Exception $e)
		{
			$db->rollback();
			$io->error('Failed to create option: ' . $e->getMessage());
			return Command::FAILURE;
		}

		$this->displaySummary($io, $option, $groupId, $groupOrder, $title, $explain);

		$action = $existingOption ? 'updated' : 'created';
		$io->success("Option '$optionId' $action successfully.");

		return Command::SUCCESS;
	}

	protected function getEditFormatChoices(string $dataType): array
	{
		$allChoices = [
			'textbox' => 'Text box - Single line input',
			'textarea' => 'Text area - Multi-line input',
			'spinbox' => 'Spin box - Number input with controls',
			'onoff' => 'On/Off - Toggle switch',
			'onofftextbox' => 'On/Off with text - Toggle with text input',
			'radio' => 'Radio buttons - Single selection',
			'select' => 'Select menu - Dropdown selection',
			'checkbox' => 'Checkboxes - Multiple selection',
			'template' => 'Template - Custom admin template',
			'callback' => 'Callback - PHP callback for rendering',
			'username' => 'Username - User autocomplete',
		];

		$excludeFormats = [];

		if ($dataType === 'array')
		{
			$excludeFormats = ['spinbox', 'onoff'];
		}
		else if ($dataType === 'boolean')
		{
			$excludeFormats = ['checkbox', 'onofftextbox', 'textarea'];
		}
		else
		{
			$excludeFormats = ['checkbox', 'onofftextbox'];
		}

		return array_diff_key($allChoices, array_flip($excludeFormats));
	}

	protected function validateDataTypeEditFormat(string $dataType, string $editFormat, SymfonyStyle $io): bool
	{
		$arrayOnlyFormats = ['checkbox', 'onofftextbox'];
		$nonArrayFormats = ['spinbox', 'onoff'];

		if ($dataType === 'array' && in_array($editFormat, $nonArrayFormats, true))
		{
			$io->error("Edit format '$editFormat' cannot be used with array data type.");
			return false;
		}

		if ($dataType !== 'array' && in_array($editFormat, $arrayOnlyFormats, true))
		{
			$io->error("Edit format '$editFormat' requires array data type.");
			return false;
		}

		return true;
	}

	protected function getAvailableGroups(): array
	{
		$groups = [];
		$finder = \XF::finder(OptionGroupFinder::class)->order('display_order');

		foreach ($finder->fetch() AS $group)
		{
			$groups[$group->group_id] = $group->group_id . ' - ' . $group->title;
		}

		return $groups;
	}

	protected function getOptionGroup(string $groupId): ?OptionGroup
	{
		return \XF::finder(OptionGroupFinder::class)
			->where('group_id', $groupId)
			->fetchOne();
	}

	protected function getExistingOption(string $optionId): ?Option
	{
		return \XF::finder(OptionFinder::class)
			->where('option_id', $optionId)
			->fetchOne();
	}

	protected function createTemplateIfNeeded(
		OutputInterface $output,
		string $templateName,
		SymfonyStyle $io
	): bool
	{
		if (!$templateName || strpos($templateName, '=') !== false)
		{
			return true;
		}

		$existing = $this->findGlobalTemplate('admin', $templateName);
		if ($existing)
		{
			return $this->validateEntityOwner($existing, $io);
		}

		return $this->runMakeCommand('xf-make:template', [
			'title' => $templateName,
			'--addon' => $this->addOnId,
			'--type' => 'admin',
		], $output, $io);
	}

	protected function displaySummary(
		SymfonyStyle $io,
		Option $option,
		string $groupId,
		int $groupOrder,
		string $title,
		string $explain
	): void
	{
		$rows = [
			['Option ID', $option->option_id],
			['Title', $title],
			['Explanation', $this->truncateText($explain, 50) ?: '(none)'],
			['Data Type', $option->data_type],
			['Edit Format', $option->edit_format],
			['Format Params', $this->truncateText($option->edit_format_params, 40) ?: '(none)'],
			['Default Value', $this->truncateText((string) $option->getValue('default_value'), 40) ?: '(none)'],
			['Group', $groupId],
			['Display Order', (string) $groupOrder],
			['Add-on', $option->addon_id],
			['Advanced', $option->advanced ? 'Yes' : 'No'],
		];

		if ($option->sub_options)
		{
			$rows[] = ['Sub-options', implode(', ', $option->sub_options)];
		}

		if ($option->validation_class)
		{
			$rows[] = ['Validation', $option->validation_class . '::' . $option->validation_method];
		}

		$io->table(['Property', 'Value'], $rows);
	}
}
