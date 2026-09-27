<?php
namespace Andrew\ModeratorPanel\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\PrintableException;

class UserNoteCategory extends AbstractController
{
    public function actionIndex()
    {
        $categoryRepo = $this->repository('Andrew\ModeratorPanel:UserNoteCategory');
        $categories = $categoryRepo->fetchCategoryList();

        $viewParams = [
            'categories' => $categories->fetch()
        ];
        return $this->view('Andrew\ModeratorPanel:UserNoteCategory\List', 'andrew_moderatorpanel_user_note_category_list', $viewParams);
    }

    public function actionAdd()
    {
        $category = $this->em()->create('Andrew\ModeratorPanel:UserNoteCategory');
        return $this->categoryAddEdit($category);
    }

    public function actionEdit(ParameterBag $params)
    {
        $category = $this->assertCategoryExists($params['note_category_id']);
        return $this->categoryAddEdit($category);
    }

    protected function categoryAddEdit($category)
    {
        $viewParams = [
            'category' => $category
        ];
        return $this->view('Andrew\ModeratorPanel:UserNoteCategory\Edit', 'andrew_moderatorpanel_user_note_category_edit', $viewParams);
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        $noteCategoryId = $params['note_category_id'];

        if ($noteCategoryId)
        {
            $category = $this->assertCategoryExists($noteCategoryId);
        }
        else
        {
            $category = $this->em()->create('Andrew\ModeratorPanel:UserNoteCategory');
        }

        $title = $this->filter('title', 'str');
        $usableUserGroups = $this->filter('usable_user_group_ids', 'array-uint');
        $usable_user_group = $this->filter('usable_user_group', 'str');
        $display_order = $this->filter('display_order', 'int');

        if ($usable_user_group == 'all')
        {
            $category->allowed_user_group_ids = [-1];
        }
        else
        {
            $category->allowed_user_group_ids = $usableUserGroups;
        }
        $category->display_order = $display_order;
        $category->title = $title;
        $category->save();

        return $this->redirect($this->buildLink('admin:moderatorpanel/user-note-categories'));
    }

    public function actionDelete(ParameterBag $params)
    {
        $noteCategoryId = $params['note_category_id'];

        $category = $this->assertCategoryExists($noteCategoryId);

        if ($this->isPost())
        {
            $category->delete();

            return $this->redirect($this->buildLink('admin:moderatorpanel/user-note-categories'));

        } else {
            $viewParams = [
                'category' => $category
            ];
            return $this->view('Andrew\ModeratorPanel:UserNoteCategory\Delete', 'andrew_moderatorpanel_user_note_category_delete', $viewParams);
        }
    }
    protected function assertCategoryExists($noteCategoryId)
    {
        return $this->assertRecordExists('Andrew\ModeratorPanel:UserNoteCategory', $noteCategoryId);
    }
}
