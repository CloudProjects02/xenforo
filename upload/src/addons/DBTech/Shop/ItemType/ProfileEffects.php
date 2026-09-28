<?php

namespace DBTech\Shop\ItemType;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;

/**
 * Class ProfileEffects
 *
 * @package DBTech\Shop\ItemType
 */
class ProfileEffects extends AbstractHandler implements ConfigurableInterface
{
    /** @var array */
    protected $defaultAdminConfig = [
        'effect_image' => '',
    ];

    /** @var array */
    protected $defaultUserConfig = [];

    /**
     * @param array $config
     *
     * @return array
     */
    public function filterAdminConfig(array $config = []): array
    {
        return $this->app()->inputFilterer()->filterArray($config, [
            'effect_image' => 'str',
        ]);
    }

    public function filterUserConfig(array $input = []): array
    {
        return [];
    }

    public function validateUserConfig(array &$configuration = [], &$errors = null): bool
    {
        return true;
    }

    public function getConfigurationForConversation(): string
    {
        return '';
    }

    public function getAdminConfigTemplate(): ?string
    {
        return 'admin:dbtech_shop_admin_config_profileeffects';
    }

    public function getUserConfigTemplate(): ?string
    {
        return null;
    }

    protected function getDefaultTemplateParams(string $context): array
    {
        $params = parent::getDefaultTemplateParams($context);
        
        return $params;
    }

    public function canRevertConfiguration(): bool
    {
        return true;
    }

    public function addListeners()
    {
        // Profile effects listeners are registered globally via the main Listener class
    }

    protected function activateAlways()
    {
        // Deactivate any other profile effects for this user
        $this->deactivateOtherEffects();
    }

    protected function deactivateOtherEffects()
    {
        // First get all profile effect items
        $profileEffectItems = $this->finder('DBTech\Shop:Item')
            ->where('item_type_id', 'profileeffects')
            ->fetchColumns('item_id');

        if (!$profileEffectItems)
        {
            return;
        }

        $itemIds = array_keys($profileEffectItems);

        // Find other active profile effect purchases for this user
        $otherPurchases = $this->finder('DBTech\Shop:Purchase')
            ->where('user_id', $this->purchase->user_id)
            ->where('purchase_id', '!=', $this->purchase->purchase_id)
            ->where('active', 1)
            ->where('item_id', $itemIds)
            ->fetch();

        foreach ($otherPurchases as $purchase)
        {
            $purchase->active = false;
            $purchase->save();
        }
    }

    protected function _discard(&$error = null): bool
    {
        return true;
    }
}