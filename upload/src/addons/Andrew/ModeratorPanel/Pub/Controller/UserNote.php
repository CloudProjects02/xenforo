<?php

namespace Andrew\ModeratorPanel\Pub\Controller;
use Andrew\ModeratorPanel\Entity\UserNoteCategory;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class UserNote extends AbstractController
{
    public function actionIndex()
    {
        $visitor = \XF::visitor();

        // Check permissions
        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewUserNotes()) {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 20;

        $filterInput = $this->filter([
            'username' => 'str',
            'givenBy' => 'str',
            'noteCategory' => 'str',
            'is_privileged' => 'str',
            'order' => 'str',
            'sortby' => 'str',

        ]);

        $username = $filterInput['username'] ?? null;
        $givenBy = $filterInput['givenBy'] ?? null;
        $order = $filterInput['order'] ?? 'desc';
        $sortby = $filterInput['sortby'] ?? 'date';
        $isPrivileged = $filterInput['is_privileged'] ?? null;
        $category = $filterInput['noteCategory'] ?? null;

        $categoryRepo = $this->repository('Andrew\ModeratorPanel:UserNoteCategory');
        $categoryFinder = $categoryRepo->fetchCategoryList();
        $categoryList = $categoryFinder->where('use_count','>',0)->fetch();

        $userNotesRepo = $this->repository('Andrew\ModeratorPanel:UserNote');
        $userNotesFinder = $userNotesRepo->fetchUserNotes($visitor, $username, $givenBy, $isPrivileged, $category, $page, $perPage, $sortby, $order);

        $total = $userNotesFinder->total();

        $userNotesFinder->limitByPage($page, $perPage);

        $viewParams = [
            'user_notes' => $userNotesFinder->fetch(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'filters' => $filterInput,
            'noteUserFilter' => $username,
            'userFilter' => $givenBy,
            'noteCategoryFilter' => $category,
            'categoryList' => $categoryList,
        ];

        return $this->view('Andrew\ModeratorPanel:UserNotes', 'andrew_moderatorpanel_user_notes', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canAddUserNote())
        {
            return $this->noPermission();
        }

        $noteId = $params->note_id;
        $withData = $this->filter('_xfWithData', 'bool');
        $inlineEdit = $this->filter('_xfInlineEdit', 'bool');
        $silent = $this->filter('silent', 'bool');
        $clear_edit = $this->filter('clear_edit', 'bool');
        $is_privileged = $this->filter('is_privileged', 'bool');
        $noteCategoryId = $this->filter('noteCategory', 'int');
        $noteList =  $this->filter('noteList', 'bool');

        $user_id = $this->filter('user_id', 'int');
        $message = $this->plugin('XF:Editor')->fromInput('message');

        if($inlineEdit)
        {
            $repo = $this->repository('Andrew\ModeratorPanel:UserNote');
            $finder = $repo->findUserNote();
            $user_note = $finder->where('note_id', $noteId)->fetchOne();

            if($silent)
            {
                if($clear_edit)
                {
                    $user_note->edit_user_id = 0;
                }
            } else {
                $user_note->last_edit_date = \XF::$time;
                $user_note->edit_user_id = $visitor->user_id;

            }


            $user_note->note_category_id = $noteCategoryId;


        } else {
            $user_note = \XF::em()->create('Andrew\ModeratorPanel:UserNote');
            $user_note->note_user_id = $user_id;
            $user_note->username = $visitor->username;
            $user_note->user_id = $visitor->user_id;
            $user_note->last_edit_date = 0;

            if($noteCategoryId)
            {
                $user_note->note_category_id = $noteCategoryId;
            }
        }

        $user_note->message = $message;
        $user_note->is_privileged = $is_privileged;

        $user_note->save();

        // Update category count is note user note category table
        if($noteCategoryId)
        {
            $categoryRepo = $this->repository('Andrew\ModeratorPanel:UserNoteCategory');
            $categoryFinder = $categoryRepo->fetchCategoryList()
                ->where('note_category_id',$noteCategoryId);

            $category = $categoryFinder->fetchOne();
            $category->last_used_date = \XF::$time;
            $category->save();
        }

        $finder = \XF::finder('XF:User');
        $user = $finder
            ->where('user_id',$user_id)
            ->fetchOne();

        if ($withData && $inlineEdit)
        {
            $viewParams = [
                'user_note' => $user_note,
                'noteList' => $noteList
            ];

            $reply = $this->view('Andrew\ModeratorPanel:UserNote\Edit', 'andrew_moderatorpanel_user_note_edit_saved', $viewParams);
            $reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
            return $reply;
        }
        else
        {
            return $this->redirect($this->buildLink('moderatorpanel/user/', $user) . '#user_notes');
        }
    }
    public function actionEdit(ParameterBag $params)
    {
        $visitor = \XF::visitor();

        $note_id = $params->note_id;
        $noteList = $this->filter('noteList', 'bool');

        $repo = $this->repository('Andrew\ModeratorPanel:UserNote');
        $finder = $repo->findUserNote();
        $user_note = $finder->where('note_id', $note_id)->fetchOne();

        if ($visitor->user_id == $user_note->user_id) {
            if (!$visitor->canEditOwnUserNote() && !$user_note->is_privileged) {
                return $this->noPermission();
            }

            if (!$visitor->canEditOwnPrivilegedUserNote() && $user_note->is_privileged) {
                return $this->noPermission();
            }
        } else {
            // Check if the visitor can edit others' notes
            if (!$visitor->canEditOtherUserNote() && !$user_note->is_privileged) {
                return $this->noPermission();
            }

            if (!$visitor->canEditOtherPrivilegedUserNote() && $user_note->is_privileged) {
                return $this->noPermission();
            }
        }

        $categoryRepo = $this->repository('Andrew\ModeratorPanel:UserNoteCategory');
        $categoryFinder = $categoryRepo->fetchCategoryList();
        $categories = $categoryFinder->fetch();

        $categories = $categories->filter(function (UserNoteCategory $category) use ($visitor) {
            return $category->isUsableByUser($visitor);
        });

        $viewParams = [
            'user_note' => $user_note,
            'noteList' => $noteList,
            'categoryList' => $categories,
            'quickEdit' => $this->filter('_xfWithData', 'bool')
        ];
        return $this->view('Andrew\ModeratorPanel:UserNote\Edit', 'andrew_moderatorpanel_user_note_edit', $viewParams);
    }
    public function actionDelete(ParameterBag $params)
    {

        $noteId = $params['note_id'];

        $user_note = $this->assertUserNoteExists($noteId);
        if(!$user_note->canDeleteNote())
        {
            return $this->noPermission();
        }

        if ($this->isPost())
        {
            $user_note->delete();
            $referer = $this->request->getServer('HTTP_REFERER');

            return $this->redirect($referer);

        } else {
            $viewParams = [
                'user_note' => $user_note
            ];
            return $this->view('Andrew\ModeratorPanel:UserNote\Delete', 'andrew_moderatorpanel_user_note_delete', $viewParams);
        }

    }

    protected function assertUserNoteExists($noteId)
    {
        return $this->assertRecordExists('Andrew\ModeratorPanel:UserNote', $noteId);
    }

}