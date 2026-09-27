<?php

namespace DBTech\Shop\ItemType;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\App;
use XF\Mvc\Entity\Manager;
use XF\Phrase;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\UserRepository;

abstract class AbstractHandler
{
	protected array $defaultAdminConfig = [];
	protected array $defaultUserConfig = [];
	protected array $listeners = [];
	protected bool $logIp = true;
	protected bool $performValidations = true;
	protected string $contentType;
	protected ?Item $item = null;
	protected ?Purchase $purchase = null;


	/**
	 * @param string $contentType
	 */
	public function __construct(string $contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @return string
	 */
	public function getContentType(): string
	{
		return $this->contentType;
	}

	/**
	 * @param Item $item
	 * @param Purchase|null $purchase
	 *
	 * @return $this
	 */
	public function setContent(Item $item, ?Purchase $purchase = null): AbstractHandler
	{
		$this->setItem($item);
		$this->setPurchase($purchase);

		return $this;
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param Item $item
	 *
	 * @return $this
	 */
	public function setItem(Item $item): AbstractHandler
	{
		if (!$item->isDeleted())
		{
			$item->code = array_replace_recursive($this->defaultAdminConfig, $item->code);
		}

		$this->item = $item;

		return $this;
	}

	/**
	 * @return Purchase
	 */
	public function getPurchase(): Purchase
	{
		return $this->purchase;
	}

	/**
	 * @param Purchase|null $purchase
	 *
	 * @return $this
	 */
	public function setPurchase(?Purchase $purchase = null): AbstractHandler
	{
		if ($purchase !== null)
		{
			if ($purchase->configuration === null)
			{
				\XF::dump($purchase);
			}
			$purchase->configuration = array_replace($this->defaultUserConfig, $purchase->configuration);
		}

		$this->purchase = $purchase;

		return $this;
	}

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_itemtype_title.' . $this->contentType);
	}

	/**
	 * @return Phrase
	 */
	public function getDescription(): Phrase
	{
		return \XF::phrase('dbtech_shop_itemtype_description.' . $this->contentType);
	}

	/**
	 * @param Item|null $item
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function renderAdminConfig(?Item $item = null): string
	{
		if ($item === null)
		{
			$item = $this->item;
		}

		if ($item === null)
		{
			// We didn't set an item in the global config either
			throw new \LogicException("No item context passed and no item context set.");
		}

		$templateName = $this->getAdminConfigTemplate();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			array_merge($this->getDefaultTemplateParams('admin_config'), ['item' => $item])
		);
	}

	/**
	 * @return string|null
	 */
	public function getAdminConfigTemplate(): ?string
	{
		return 'public:dbtech_shop_admin_config_' . $this->contentType;
	}

	/**
	 * @param array $config
	 *
	 * @return array
	 */
	public function filterAdminConfig(array $config = []): array
	{
		return $config;
	}

	/**
	 * @param Purchase|null $purchase
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function renderUserConfig(?Purchase $purchase = null): string
	{
		if ($purchase === null)
		{
			$purchase = $this->purchase;
		}

		if ($purchase === null)
		{
			// We didn't set a purchase in the global config either
			throw new \LogicException("No purchase context passed and no purchase context set.");
		}

		$templateName = $this->getUserConfigTemplate();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			array_merge($this->getDefaultTemplateParams('user_config'), [
				'purchase' => $purchase,
				'item' => $purchase->Item,
			])
		);
	}

	/**
	 * @return string
	 */
	public function getUserConfigTemplate(): string
	{
		return 'public:dbtech_shop_user_config_' . $this->contentType;
	}

	/**
	 * @param Purchase|null $purchase
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function renderUserConfigForView(?Purchase $purchase = null): string
	{
		if ($purchase === null)
		{
			$purchase = $this->purchase;
		}

		if ($purchase === null)
		{
			// We didn't set a purchase in the global config either
			throw new \LogicException("No purchase context passed and no purchase context set.");
		}

		/*
		 * Pre-defined configuration items should still display the configuration
		if (!$purchase->canConfigure())
		{
			return '';
		}
		*/

		$templateName = $this->getUserConfigViewTemplate();
		if (!$templateName)
		{
			return '';
		}
		return \XF::app()->templater()->renderTemplate(
			$templateName,
			array_merge($this->getDefaultTemplateParams('user_config_view'), [
				'purchase' => $purchase,
				'item' => $purchase->Item,
			])
		);
	}

	/**
	 * @return string
	 */
	public function getUserConfigViewTemplate(): string
	{
		return 'public:dbtech_shop_user_config_view_' . $this->contentType;
	}

	/**
	 * @return PreEscaped|string
	 */
	public function getConfigurationForConversation(): PreEscaped|string
	{
		return '';
	}

	/**
	 * @param string $context
	 *
	 * @return array
	 */
	protected function getDefaultTemplateParams(string $context): array
	{
		return [
			'title' => $this->getTitle(),
		];
	}

	/**
	 * @return bool
	 */
	public function canRevertConfiguration(): bool
	{
		// Return false if the change is permanent, such as username change
		return true;
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return true;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return AbstractHandler
	 */
	public function logIp(bool $logIp): AbstractHandler
	{
		$this->logIp = $logIp;

		return $this;
	}

	/**
	 * @param bool $perform
	 *
	 * @return AbstractHandler
	 */
	public function setPerformValidations(bool $perform): AbstractHandler
	{
		$this->performValidations = $perform;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getPerformValidations(): bool
	{
		return $this->performValidations;
	}

	/**
	 * @return bool
	 */
	public function isConfigurable(): bool
	{
		if (!($this instanceof ConfigurableInterface))
		{
			// Item is not configurable
			return false;
		}

		return true;
	}

	/**
	 * @throws PrintableException
	 */
	public function afterPurchase(): void
	{
		if (!$this->purchase)
		{
			// We didn't set a purchase in the global config
			throw new \LogicException("Cannot activate a purchase without a purchase context set.");
		}

		if (!$this->item)
		{
			// We didn't set an item in the global config
			throw new \LogicException("Cannot activate a purchase without an item context set.");
		}

		if ($this->purchase->isActive())
		{
			if ($this->performValidations)
			{
				if ($this->item->isOnlyGiftable() && !$this->purchase->gifted)
				{
					return;
				}
			}

			$this->activate();
		}
	}

	/**
	 * @param array $configuration
	 *
	 * @throws PrintableException
	 */
	public function configure(array $configuration = []): void
	{
		if (!$this->purchase)
		{
			// We didn't set a purchase in the global config
			throw new \LogicException("Cannot configure a purchase without a purchase context set.");
		}

		if (!$this->item)
		{
			// We didn't set an item in the global config
			throw new \LogicException("Cannot configure a purchase without an item context set.");
		}

		$purchase = $this->purchase;
		$item = $this->item;

		if (!($this instanceof ConfigurableInterface))
		{
			// Item is not configurable
			throw new \LogicException("The item {$this->item->title} does not implement ConfigurableInterface.");
		}

		if ($purchase->configured && $this->canRevertConfiguration())
		{
			// Revert the old configuration, if we have configured previously, and we *can* deactivate
			$this->_deactivate();
		}

		$purchase->configuration = $configuration;

		$wasConfigured = $purchase->configured;
		$hadConfigurationChanges = $purchase->isChanged('configuration');

		$purchase->configured = true;
		$purchase->active = true;
		$purchase->saveIfChanged();

		if ($hadConfigurationChanges)
		{
			// Only run post-configure action if we actually changed configuration
			$this->afterConfiguration($wasConfigured);
		}

		$this->activateAlways();

		if ($hadConfigurationChanges)
		{
			// Only send configuration notice if we had actual configuration changes
			\XF::app()->repository(PurchaseRepository::class)
				->sendConfigurationNotifications($item, $purchase)
			;
		}

		if (!$item->canReConfigure() && $item->getFlag('auto_discard'))
		{
			$this->setPerformValidations(false)
				->logIp(false)
				->discard($null, 'auto_discard')
			;
		}
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function activate(&$error = null): bool
	{
		if (!$this->purchase)
		{
			// We didn't set a purchase in the global config
			throw new \LogicException("Cannot activate a purchase without a purchase context set.");
		}

		if (!$this->item)
		{
			// We didn't set an item in the global config
			throw new \LogicException("Cannot activate a purchase without an item context set.");
		}

		$item = $this->item;
		$purchase = $this->purchase;

		if ($this->performValidations)
		{
			if ($item->isOnlyGiftable() && !$purchase->gifted)
			{
				$error = \XF::phraseDeferred('dbtech_shop_item_only_giftable_has_not_been_gifted');
				return false;
			}

			if ($purchase->isExpired())
			{
				$error = \XF::phraseDeferred('dbtech_shop_cannot_activate_expired_purchase');
				return false;
			}
		}

		$this->activateAlways();

		$db = \XF::app()->db();

		$db->beginTransaction();

		$purchase = $this->purchase;
		$purchase->active = true;

		if (!$purchase->preSave())
		{
			$error = $purchase->getErrors();
			$purchase->reset();
			$db->rollback();
			return false;
		}

		$purchase->save(true, false);

		$db->commit();

		return true;
	}

	/**
	 * @param bool $wasConfigured
	 */
	protected function afterConfiguration(bool $wasConfigured = false)
	{
	}

	/**
	 *
	 */
	protected function activateAlways()
	{
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function deactivate(&$error = null): bool
	{
		if (!$this->purchase)
		{
			// We didn't set a purchase in the global config
			throw new \LogicException("Cannot deactivate a purchase without a purchase context set.");
		}

		if (!$this->item)
		{
			// We didn't set an item in the global config
			throw new \LogicException("Cannot deactivate a purchase without an item context set.");
		}

		$this->_deactivate($error);
		if (!empty($error))
		{
			return false;
		}

		$db = \XF::app()->db();

		$db->beginTransaction();

		$purchase = $this->purchase;
		$purchase->active = false;

		if (!$purchase->preSave())
		{
			$error = $purchase->getErrors();
			$purchase->reset();
			$db->rollback();
			return false;
		}

		$purchase->save(true, false);

		$db->commit();

		return true;
	}

	/**
	 * @param null $error
	 */
	protected function _deactivate(&$error = null)
	{
	}

	/**
	 * @param null $error
	 * @param string $reason
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function discard(&$error = null, string $reason = 'manual'): bool
	{
		if (!$this->purchase)
		{
			// We didn't set a purchase in the global config
			throw new \LogicException("Cannot discard a purchase without a purchase context set.");
		}

		if (!$this->item)
		{
			// We didn't set an item in the global config
			throw new \LogicException("Cannot discard a purchase without an item context set.");
		}

		if ($this->performValidations && $this->purchase->isActive())
		{
			$retval = $this->deactivate($error);
			if ($retval === false)
			{
				return false;
			}
		}

		$retval = $this->_discard($error);
		if ($retval === false)
		{
			return false;
		}

		$db = \XF::app()->db();

		$db->beginTransaction();

		$purchase = $this->purchase;
		if ($purchase->hasChanges())
		{
			if (!$purchase->preSave())
			{
				$error = $purchase->getErrors();
				$purchase->reset();
				$db->rollback();
				return false;
			}

			$purchase->save(true, false);
		}

		$user = $purchase->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser();

		// If we're not performing validations, we also don't want to log IPs
		\XF::app()->repository(PurchaseRepository::class)
			->logTransaction(
				$purchase,
				'discard',
				0,
				$user,
				null,
				$this->logIp,
				['reason' => $reason]
			)
		;

		if (!$purchase->preDelete())
		{
			$error = $purchase->getErrors();
			$purchase->reset();
			$db->rollback();
			return false;
		}

		$purchase->delete(true, false);

		$db->commit();

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function _discard(&$error = null): bool
	{
		return true;
	}

	/**
	 * @param string $event
	 * @param array $args
	 * @param string|null $hint
	 *
	 * @return bool
	 */
	public function fire(string $event, array $args = [], ?string $hint = null): bool
	{
		$listeners = $this->listeners;

		if (empty($listeners[$event]))
		{
			return true;
		}

		if (!empty($listeners[$event]['_']))
		{
			foreach ($listeners[$event]['_'] AS $callback)
			{
				if (is_callable($callback))
				{
					$return = call_user_func_array($callback, $args);
					if ($return === false)
					{
						return false;
					}
				}
			}
		}

		if ($hint !== null)
		{
			if ($hint !== '_' && !empty($listeners[$event][$hint]))
			{
				foreach ($listeners[$event][$hint] AS $callback)
				{
					if (is_callable($callback))
					{
						$return = call_user_func_array($callback, $args);
						if ($return === false)
						{
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * @param string $event
	 * @param \Closure $callback
	 * @param string|null $hint
	 */
	public function addListener(string $event, \Closure $callback, ?string $hint = '_'): void
	{
		$this->listeners[$event][$hint][] = $callback;
	}

	/**
	 *
	 */
	public function addListeners()
	{
	}

	/**
	 * @return \ArrayObject
	 */
	protected function options(): \ArrayObject
	{
		return \XF::app()->options();
	}

	/**
	 * @return Manager
	 */
	protected function em(): Manager
	{
		return \XF::app()->em();
	}

	/**
	 * @return App
	 */
	protected function app(): App
	{
		return \XF::app();
	}
}