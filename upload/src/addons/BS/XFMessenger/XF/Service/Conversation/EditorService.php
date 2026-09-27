<?php

namespace BS\XFMessenger\XF\Service\Conversation;

use BS\XFMessenger\Service\Conversation\Wallpaper;
use XF\Entity\ConversationMaster;
use XF\Http\Upload;

class EditorService extends XFCP_EditorService
{
    protected Wallpaper $wallpaperService;

    public function __construct(\XF\App $app, ConversationMaster $conversation)
    {
        parent::__construct($app, $conversation);

        $this->wallpaperService = $this->service('BS\XFMessenger:Conversation\Wallpaper', $conversation);
    }

    public function updateWallpaper(
        array $options,
        ?Upload $wallpaper = null,
        ?\XF\Entity\User $forUser = null,
        ?array &$errors = []
    ) {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->setOptions($options);

        if ($wallpaper) {
            if (! $this->wallpaperService->setImageFromUpload($wallpaper)) {
                $errors = [$this->wallpaperService->getError()];
                return;
            }

            $this->wallpaperService->updateWallpaper();
        } else {
            $this->wallpaperService->updateOptions();
        }

        $this->wallpaperService->setUser(null);
    }

    public function deleteWallpaper(?\XF\Entity\User $forUser = null, array $options = [])
    {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->deleteWallpaper();

        if ($options) {
            $this->wallpaperService->setOptions($options);
            $this->wallpaperService->updateOptions();
        }

        $this->wallpaperService->setUser(null);
    }

    public function resetWallpaper(?\XF\Entity\User $forUser = null)
    {
        if ($forUser) {
            $this->wallpaperService->setUser($forUser);
        }

        $this->wallpaperService->setOptions([
            'theme_index' => -1,
        ]);
        $this->wallpaperService->deleteWallpaper();
        $this->wallpaperService->updateOptions();

        $this->wallpaperService->setUser(null);
    }
}
