<?php

namespace Andrew\ModeratorPanel;

class Listener
{
    public static function userDeleteCleanInit(\XF\Service\User\DeleteCleanUp $deleteService, array &$deletes)
    {
        $deletes['xf_andrew_mp_user_note'] = 'note_user_id = ?';
    }
}