<?php

namespace BS\RealTimeChat\Entity;

use BS\RealTimeChat\Broadcasting\Broadcast;
use BS\RealTimeChat\Concerns\Repos;
use BS\RealTimeChat\Utils\RoomTag;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $room_id
 * @property string $type
 * @property string $tag
 * @property string $description
 * @property int $avatar_date
 * @property int $wallpaper_date
 * @property array|null $wallpaper_options
 * @property int|null $user_id
 * @property int $members_count
 * @property array|null $members
 * @property int $last_message_id
 * @property int $last_message_date
 * @property int $last_message_user_id
 * @property bool $pinned
 * @property int $pin_order
 * @property bool $allowed_replies
 * @property int $created_date
 *
 * GETTERS
 * @property mixed $route_tag
 * @property mixed $tag_parts
 * @property mixed $tag_prefix
 * @property mixed $tag_name
 * @property mixed $member_pin_order
 * @property mixed $wallpaper
 * @property mixed $wallpaper_type
 * @property mixed $theme
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\RealTimeChat\Entity\RoomMember[] $Members
 * @property \BS\RealTimeChat\Entity\Message $LastMessage
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\RealTimeChat\Entity\RoomLink[] $Links
 * @property \XF\Mvc\Entity\AbstractCollection|\BS\RealTimeChat\Entity\Ban[] $Bans
 */
class Room extends Entity
{
    use Repos;

    public const TYPE_PUBLIC = 'public';
    public const TYPE_MEMBER = 'member';

    public function isPinned()
    {
        if ($this->pinned) {
            return true;
        }

        $member = $this->getMember(\XF::visitor());
        if (! $member->exists()) {
            return false;
        }

        return $member->room_pinned;
    }

    public function isOwner(User $user)
    {
        return $this->user_id === $user->user_id;
    }

    public function isBanned(User $user)
    {
        return ! empty($this->Bans[$user->user_id]);
    }

    public function isDeletable()
    {
        if (! $this->user_id && $this->isPublic()) {
            return false;
        }

        return true;
    }

    public function isPublic(): bool
    {
        return $this->type === self::TYPE_PUBLIC;
    }

    public function isMemberType(): bool
    {
        return $this->type === self::TYPE_MEMBER;
    }

    public function canView(User $user = null, &$error = null)
    {
        $user ??= \XF::visitor();

        if ($this->isBanned($user)) {
            $ban = $this->Bans[$user->user_id];
            $error = (string)$ban->ban_title;
            return false;
        }

        if ($this->isPublic()) {
            return true;
        }

        return ! empty($this->Members[$user->user_id]);
    }

    public function canReactMessage(Message $message, User $user = null, &$error = null)
    {
        $user ??= \XF::visitor();
        return $user->hasChatPermission('canReact');
    }

    public function canJoin(User $user = null)
    {
        if ($this->isPublic()) {
            return false;
        }

        $user ??= \XF::visitor();
        $existsMember = $this->getMember($user)->exists();

        if ($existsMember) {
            return false;
        }

        // Limit the number of members
        // This is to prevent performance issues because we are sending events to each user in member rooms
        $roomMaxMembers = $this->app()->options()->rtcRoomMaxMembers;
        if ($roomMaxMembers > 0 && $this->members_count >= $roomMaxMembers) {
            return false;
        }

        return true;
    }

    public function canLeave(User $user = null)
    {
        if ($this->isPublic()) {
            return false;
        }

        $user ??= \XF::visitor();
        return $this->getMember($user)->exists();
    }

    public function canDelete(User $user = null)
    {
        // Only default public room has no user_id
        // It prevents deleting public room
        if (! $this->user_id) {
            return false;
        }

        if ($this->isPublic()) {
            return false;
        }

        $user ??= \XF::visitor();

        if ($this->isOwner($user)
            && $user->hasChatPermission('deleteOwnRooms')
        ) {
            return true;
        }

        return $user->hasChatPermission('deleteAnyRoom');
    }

    public function canEdit(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($this->isOwner($user)
            && $user->hasChatPermission('editOwnRooms')
        ) {
            return true;
        }

        return $user->hasChatPermission('editAnyRoom');
    }

    public function canPostMessage(User $user = null)
    {
        $user ??= \XF::visitor();

        if (! $this->allowed_replies && ! $this->isOwner($user)) {
            return false;
        }

        if (! $user->hasAccessToChatRoom($this)) {
            return false;
        }

        return $user->hasChatPermission('canWrite');
    }

    public function canUploadAttachments(User $user = null)
    {
        $user ??= \XF::visitor();
        return $user->hasChatPermission('uploadAttachment');
    }

    public function canEditType()
    {
        // Prevent editing type of default public room
        if (! $this->user_id) {
            return false;
        }

        return true;
    }

    public function canViewBannedList(User $user = null)
    {
        $user ??= \XF::visitor();
        return $user->hasChatPermission('viewBannedList');
    }

    public function canBan(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($user->hasChatPermission('canBanAny')) {
            return true;
        }

        if ($user->hasChatPermission('canBan') && $this->isOwner($user)) {
            return true;
        }

        return false;
    }

    public function canLiftBan(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($user->hasChatPermission('canBanAny')) {
            return true;
        }

        if ($user->hasChatPermission('canBan') && $this->isOwner($user)) {
            return true;
        }

        return false;
    }

    public function canGetNewLink(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($this->isPublic()) {
            return false;
        }

        return $this->canEdit($user);
    }

    public function canPin(User $user = null)
    {
        // todo pin command

        $user ??= \XF::visitor();

        if ($this->isPublic()) {
            return false;
        }

        return $this->isMember($user);
    }

    public function canClear(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($this->isOwner($user)
            && $user->hasChatPermission('clearOwnRooms')
        ) {
            return true;
        }

        return $user->hasChatPermission('clearAnyRoom');
    }

    public function canSetWallpaper(User $user = null)
    {
        $user ??= \XF::visitor();

        if ($this->isPublic()) {
            return $this->canEdit($user);
        }

        if ($user->user_id === $this->user_id
            && $user->hasChatPermission('setWallpaperInOwnRooms')
        ) {
            return true;
        }

        return $user->hasChatPermission('editAnyRoom');
    }

    public function canSetIndividualWallpaper(User $user = null)
    {
        if (! $this->allowed_replies) {
            return false;
        }

        $user ??= \XF::visitor();
        return $user->hasChatPermission('setIndividualWallpaper');
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

    public function canTranslate()
    {
        return \XF::visitor()->hasChatPermission('translateMessages');
    }

    public function messageAdded(Message $message)
    {
        $this->updateLastMessage($message);

        if ($message->User) {
            $member = $this->getMember($message->User);

            $member->touchLastViewDate($message->message_date_);
            $member->touchLastReplyDate($message->message_date_);
        }

        $this->saveIfChanged();
    }

    public function messageDeleted(Message $message)
    {
        if ($this->last_message_id === $message->message_id) {
            // find the new last message
            /** @var \BS\RealTimeChat\Entity\Message $newLastMessage */
            $newLastMessage = $this->finder('BS\RealTimeChat:Message')
                ->where('message_id', '<>', $message->message_id)
                ->where('pm_user_id', 0)
                ->where('room_id', $this->room_id)
                ->order('message_date', 'DESC')
                ->fetchOne();

            if ($newLastMessage) {
                $this->updateLastMessage($newLastMessage);
            } else {
                $this->updateLastMessage();
            }

            $this->saveIfChanged();
        }
    }

    public function updateLastMessage(?Message $message = null)
    {
        if ($message) {
            if ($message->isPm()) {
                return;
            }

            $this->last_message_id = $message->message_id;
            $this->last_message_date = $message->message_date_;
            $this->last_message_user_id = $message->user_id;
            return;
        }

        $this->last_message_id = 0;
        $this->last_message_date = 0;
        $this->last_message_user_id = 0;
    }

    /**
     * @param  \XF\Entity\User  $user
     * @return \BS\RealTimeChat\Entity\RoomMember
     */
    public function getMember(User $user)
    {
        $member = $this->Members[$user->user_id];

        if (! $member) {
            /** @var \BS\RealTimeChat\Entity\RoomMember $member */
            $member = $this->_em->create('BS\RealTimeChat:RoomMember');
            $member->room_id = $this->room_id;
            $member->user_id = $user->user_id;

            $member->hydrateRelation('Room', $this);
            $member->hydrateRelation('User', $user);
        }

        return $member;
    }

    public function isMember(User $user)
    {
        return $this->getMember($user)->exists();
    }

    public function getNewMessage(?User $fromUser = null)
    {
        /** @var \BS\RealTimeChat\Entity\Message $message */
        $message = $this->_em->create('BS\RealTimeChat:Message');
        $message->room_id = $this->room_id;
        $message->room_tag = $this->tag;

        $message->hydrateRelation('Room', $this);

        if ($fromUser) {
            $message->user_id = $fromUser->user_id;
            $message->hydrateRelation('User', $fromUser);
        }

        return $message;
    }

    public function getNewRoomLink(?User $fromUser = null)
    {
        /** @var \BS\RealTimeChat\Entity\RoomLink $roomLink */
        $roomLink = $this->_em->create('BS\RealTimeChat:RoomLink');
        $roomLink->room_id = $this->room_id;

        if ($fromUser) {
            $roomLink->user_id = $fromUser->user_id;
        }

        return $roomLink;
    }

    public function getAbstractedCustomAvatarPath($size)
    {
        return sprintf(
            'data://rtc-room-avatars/%s/%d/%d.jpg',
            $size,
            floor($this->room_id / 1000),
            $this->room_id
        );
    }

    public function getAvatarType()
    {
        if ($this->avatar_date) {
            return 'custom';
        }

        return 'default';
    }

    public function getAvatarUrl(string $sizeCode, bool $canonical = false)
    {
        $app = $this->app();

        $sizeMap = $app->container('avatarSizeMap');
        if (! isset($sizeMap[$sizeCode])) {
            // Always fallback to 's' in the event of an unknown size (e.g. 'xs', 'xxs' etc.)
            $sizeCode = 's';
        }

        if ($this->avatar_date) {
            $group = floor($this->room_id / 1000);
            return $app->applyExternalDataUrl(
                "rtc-room-avatars/{$sizeCode}/{$group}/{$this->room_id}.jpg?{$this->avatar_date}",
                $canonical
            );
        }

        return null;
    }

    public function getAbstractedCustomWallpaperPath(?int $userId = null)
    {
        return sprintf(
            'data://rtc-room-wallpapers/%d/%s.jpg',
            floor($this->room_id / 1000),
            $this->room_id.($userId ? '-'.$userId : '')
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
                $fileName = "{$this->room_id}-{$member->user_id}.jpg";
                $wallpaperDate = $member->room_wallpaper_date;
                break;
            case 'custom':
                $fileName = "{$this->room_id}.jpg";
                $wallpaperDate = $this->wallpaper_date;
                break;
            default:
                break;
        }

        $group = floor($this->room_id / 1000);

        if ($wallpaperDate) {
            return $app->applyExternalDataUrl(
                "rtc-room-wallpapers/{$group}/{$fileName}?{$wallpaperDate}",
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

        if ($this->wallpaper_date) {
            return 'custom';
        }

        if (! $member->exists()) {
            return 'default';
        }

        if ($member->room_wallpaper_date) {
            return 'member_custom';
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

    protected function verifyTag(&$tag)
    {
        if (empty($tag)) {
            $this->error(\XF::phrase('rtc_tag_cannot_be_empty'));
            return false;
        }

        if (! RoomTag::matchRegex($tag)) {
            $this->error(\XF::phrase('rtc_please_enter_valid_tag'));
            return false;
        }

        return true;
    }

    public function getRouteTag()
    {
        return RoomTag::urlEncode($this->tag);
    }

    public function getTagPrefix()
    {
        return $this->tag_parts[0];
    }

    public function getTagName()
    {
        return $this->tag_parts[1];
    }

    public function getTagParts()
    {
        return explode('/', $this->tag);
    }

    public function getMemberPinOrder()
    {
        if ($this->pinned) {
            return $this->pin_order;
        }

        $member = $this->getMember(\XF::visitor());
        if ($member->room_pinned && $member->exists()) {
            return $member->room_pin_order;
        }

        return 0;
    }

    protected function _preSave()
    {
        if ($this->isInsert() && $this->findExistingRoomByTag()) {
            $this->error(\XF::phrase('rtc_tag_must_be_unique'));
        }

        if ($this->isChanged('type') && ! $this->canEditType()) {
            $this->error(\XF::phrase('rtc_type_of_this_room_cannot_be_changed'));
        }
    }

    protected function _postSave()
    {
        if ($this->isChanged('tag')
            && ($oldTag = $this->getPreviousValue('tag'))
        ) {
            $this->getMessageRepo()
                ->updateMessagesRoomTag($oldTag, $this->tag);
        }

        if ($this->isUpdate() && $this->isOneOfBroadcastColumnsChanged()) {
            $this->broadcastUpdate();
        }

        if ($this->isUpdate() && $this->getOption('log_moderator')) {
            $this->app()->logger()->logModeratorChanges('rtc_room', $this);
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
            'tag',
            'type',
            'avatar_date',
            'wallpaper_date',
            'wallpaper_options',
            'pinned',
            'pin_order',
            'allowed_replies',
        ];
    }

    public function broadcastUpdate()
    {
        Broadcast::roomUpdated($this);
    }

    protected function _postDelete()
    {
        $this->getRoomRepo()->cleanUpAfterDeleteRoom($this);

        Broadcast::roomsDeleted([
            'room' => $this
        ], [
            'tags' => [$this->tag]
        ]);

        if ($this->getOption('log_moderator')) {
            $this->app()->logger()->logModeratorAction('rtc_room', $this, 'delete');
        }
    }

    protected function findExistingRoomByTag()
    {
        return $this->finder('BS\RealTimeChat:Room')
            ->where('tag', $this->tag)
            ->where('room_id', '!=', $this->room_id)
            ->fetchOne();
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_bs_chat_room';
        $structure->shortName = 'BS\RealTimeChat:Room';
        $structure->primaryKey = 'room_id';
        $structure->contentType = 'rtc_room';
        $structure->columns = [
            'room_id'              => ['type' => self::UINT, 'autoIncrement' => true],
            'type'                 => ['type' => self::STR, 'maxLength' => 25, 'default' => 'member'],
            'tag'                  => [
                'type'      => self::STR,
                'maxLength' => 50,
                'required'  => true
            ],
            'description'          => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
            'avatar_date'          => ['type' => self::UINT, 'default' => 0],
            'wallpaper_date'       => ['type' => self::UINT, 'default' => 0],
            'wallpaper_options'    => ['type' => self::JSON_ARRAY, 'default' => null, 'nullable' => true],
            'user_id'              => ['type' => self::UINT, 'default' => null, 'nullable' => true],
            'members_count'        => ['type' => self::UINT, 'default' => 0],
            'members'              => ['type' => self::JSON_ARRAY, 'default' => [], 'nullable' => true],
            'last_message_id'      => ['type' => self::UINT, 'default' => 0],
            'last_message_date'    => ['type' => self::UINT, 'default' => 0, 'max' => pow(2, 63) - 2],
            'last_message_user_id' => ['type' => self::UINT, 'default' => 0],
            'pinned'               => ['type' => self::BOOL, 'default' => 0],
            'pin_order'            => ['type' => self::UINT, 'default' => 0],
            'allowed_replies'      => ['type' => self::BOOL, 'default' => true],
            'created_date'         => ['type' => self::UINT, 'default' => \XF::$time],
        ];
        $structure->getters = [
            'route_tag'        => true,
            'tag_parts'        => true,
            'tag_prefix'       => true,
            'tag_name'         => true,
            'member_pin_order' => true,
            'wallpaper'        => true,
            'wallpaper_type'   => true,
            'theme'            => true,
        ];
        $structure->relations = [
            'User'        => [
                'entity'     => 'XF:User',
                'type'       => self::TO_ONE,
                'conditions' => 'user_id',
                'primary'    => true
            ],
            'Members'     => [
                'entity'     => 'BS\RealTimeChat:RoomMember',
                'type'       => self::TO_MANY,
                'conditions' => 'room_id',
                'key'        => 'user_id'
            ],
            'LastMessage' => [
                'entity'     => 'BS\RealTimeChat:Message',
                'type'       => self::TO_ONE,
                'conditions' => [
                    ['message_id', '=', '$last_message_id'],
                    ['room_id', '=', '$room_id']
                ]
            ],
            'Links'       => [
                'entity'     => 'BS\RealTimeChat:RoomLink',
                'type'       => self::TO_MANY,
                'conditions' => 'room_id',
                'key'        => 'link_id'
            ],
            'Bans'        => [
                'entity'     => 'BS\RealTimeChat:Ban',
                'type'       => self::TO_MANY,
                'conditions' => 'room_id',
                'key'        => 'user_id'
            ],
        ];
        $structure->options = [
            'log_moderator' => true
        ];

        return $structure;
    }
}
