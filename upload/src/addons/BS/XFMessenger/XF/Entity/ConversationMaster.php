<?php

namespace BS\XFMessenger\XF\Entity;

use BS\XFMessenger\Broadcasting\Broadcast;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $wallpaper_date
 * @property array|null $wallpaper_options
 *
 * GETTERS
 * @property mixed $wallpaper
 * @property mixed $wallpaper_type
 */
class ConversationMaster extends XFCP_ConversationMaster
{
    public function canSetWallpaper()
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id === $this->user_id
            && $visitor->hasPermission('conversation', 'xfmSetWallpaper')
        ) {
            return true;
        }

        return $this->canEdit();
    }

    public function canSetIndividualWallpaper()
    {
        return \XF::visitor()->hasPermission('conversation', 'xfmSetIndividualWallpaper');
    }

    public function canDeleteWallpaper()
    {
        if ($this->wallpaper_type === 'default') {
            return false;
        }

        $visitor = \XF::visitor();

        switch ($this->wallpaper_type) {
            case 'default':
                return false;
            case 'custom':
                return $this->wallpaper_date && $this->user_id === $visitor->user_id;
            case 'member_custom':
                return $this->getMember($visitor)->room_wallpaper_date;
        }

        return false;
    }

    public function canResetWallpaper()
    {
        return $this->wallpaper_type !== 'default'
            && strpos($this->wallpaper_type, 'member') === 0;
    }

    public function canResetRoomWallpaper()
    {
        if (! $this->canEdit()) {
            return false;
        }

        return $this->wallpaper_type !== 'default'
            && strpos($this->wallpaper_type, 'member') === false;
    }

    public function broadcastNewRoom()
    {
        Broadcast::newRoom($this);
    }

    public function broadcastUpdate()
    {
        Broadcast::roomUpdated($this);
    }

    public function getAbstractedCustomWallpaperPath(?int $userId = null)
    {
        return sprintf(
            'data://xfm-conversation-wallpapers/%d/%s.jpg',
            floor($this->conversation_id / 1000),
            $this->conversation_id.($userId ? '-'.$userId : '')
        );
    }

    public function getWallpaper()
    {
        $url = $this->getWallpaperUrl();
        $options = $this->getWallpaperOptions();
        $type = $this->wallpaper_type;

        return compact('url', 'options', 'type');
    }

    public function getWallpaperUrl(bool $canonical = false)
    {
        $app = $this->app();

        $wallpaperDate = 0;
        $fileName = '';

        switch ($this->wallpaper_type) {
            case 'member_custom':
                $member = $this->getMember(\XF::visitor());
                $fileName = "{$this->conversation_id}-{$member->owner_user_id}.jpg";
                $wallpaperDate = $member->room_wallpaper_date;
                break;
            case 'custom':
                $fileName = "{$this->conversation_id}.jpg";
                $wallpaperDate = $this->wallpaper_date;
                break;
            default:
                break;
        }

        $group = floor($this->conversation_id / 1000);

        if ($wallpaperDate) {
            return $app->applyExternalDataUrl(
                "xfm-conversation-wallpapers/{$group}/{$fileName}?{$wallpaperDate}",
                $canonical
            );
        }

        return null;
    }

    public function getTheme()
    {
        return $this->app()->templater()->func(
            'rtc_room_theme',
            [['wallpaper' => $this->wallpaper]]
        );
    }

    protected function getWallpaperOptions()
    {
        if (in_array($this->wallpaper_type, ['member_custom', 'member_custom_theme'])) {
            $options = $this->getMember(\XF::visitor())->room_wallpaper_options;
        } else {
            $options = $this->wallpaper_options;
        }

        if (! isset($options['theme_index'])) {
            $options['theme_index'] = -1;
        }

        return (array)$options;
    }

    public function getWallpaperType()
    {
        $member = $this->getMember(\XF::visitor());

        if ($member->room_wallpaper_date) {
            return 'member_custom';
        }

        if (! $member || ! $member->exists()) {
            return 'default';
        }

        if ($this->wallpaper_date) {
            return 'custom';
        }

        $memberWallpaperOptions = $member->room_wallpaper_options;
        $roomWallpaperOptions = $this->wallpaper_options;

        $memberThemeIndex = $memberWallpaperOptions['theme_index'] ?? -1;
        $roomThemeIndex = $roomWallpaperOptions['theme_index'] ?? -1;
        if ($memberThemeIndex !== -1 && $memberThemeIndex !== $roomThemeIndex) {
            return 'member_custom_theme';
        }

        return 'default';
    }

    public function getMember(\XF\Entity\User $user)
    {
        return $this->Users[$user->user_id];
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isChanged('last_message_id')) {
            \XF::repository('BS\XFMessenger:Conversation')
                ->rebuildUnreadCount($this);
        }

        if ($this->isUpdate() && $this->isOneOfBroadcastColumnsChanged()) {
            $this->broadcastUpdate();
        }
    }

    protected function isOneOfBroadcastColumnsChanged()
    {
        $broadcastColumns = $this->getBroadcastColumns();
        foreach ($broadcastColumns as $column) {
            if ($this->isChanged($column)) {
                return true;
            }
        }

        return false;
    }

    protected function getBroadcastColumns()
    {
        return [
            'title',
            'open_invite',
            'conversation_open',
            'wallpaper_date',
            'wallpaper_options',
        ];
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['wallpaper_date'] = ['type' => self::UINT, 'default' => 0];
        $structure->columns['wallpaper_options'] = ['type' => self::JSON_ARRAY, 'default' => []];

        $structure->getters['wallpaper'] = true;
        $structure->getters['wallpaper_type'] = true;
        $structure->getters['theme'] = true;

        return $structure;
    }
}
