<?php

namespace DBTech\Shop\ItemType;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Purchase;

/**
 * Class AvatarFrame
 *
 * @package DBTech\Shop\ItemType
 */
class AvatarFrame extends AbstractHandler implements ConfigurableInterface
{
    /** @var array */
    protected $defaultAdminConfig = [
        'frame_image' => '',
        'frame_size' => '150',
        'frame_border_radius' => '50',
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
            'frame_image' => 'str',
            'frame_size' => 'uint',
            'frame_border_radius' => 'uint',
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
        return 'admin:dbtech_shop_admin_config_avatarframe';
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
        // Avatar frame listeners are registered globally via the main Listener class
    }

    protected function activateAlways()
    {
        // Deactivate any other avatar frames for this user
        $this->deactivateOtherFrames();
    }

    protected function deactivateOtherFrames()
    {
        // First get all avatar frame items
        $avatarFrameItems = $this->finder('DBTech\Shop:Item')
            ->where('item_type_id', 'avatarframe')
            ->fetchColumns('item_id');

        if (!$avatarFrameItems)
        {
            return;
        }

        $itemIds = array_keys($avatarFrameItems);

        // Find other active avatar frame purchases for this user
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