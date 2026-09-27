<?php

namespace Andrew\ModeratorPanel\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class UserNote extends Repository
{
    public function findUserNote()
    {
        $visitor = \XF::visitor();
        $finder = $this->finder('Andrew\ModeratorPanel:UserNote')
            ->with('Category');
        $finder->setDefaultOrder('note_id', 'DESC');

        if (!$visitor->canViewPrivilegedUserNotes()) {
            $finder->where('is_privileged', false);
        }

        // Fetch all categories and filter them based on usability by the visitor
        $categoryFinder = \XF::finder('Andrew\ModeratorPanel:UserNoteCategory');
        $categories = $categoryFinder->fetch();

        $usableCategoryIds = [];
        foreach ($categories as $category) {
            if ($category->isUsableByUser($visitor)) {
                $usableCategoryIds[] = $category->note_category_id;
            }
        }

        if (!empty($usableCategoryIds)) {
            $finder->whereOr([
                ['note_category_id', 0],
                ['note_category_id', $usableCategoryIds]
            ]);
        } else {
            // If no usable categories, only include notes with no category
            $finder->where('note_category_id', 0);
        }

        return $finder;
    }

    public function fetchUserNotes($visitor, $username = null, $givenBy = null, $isPrivileged = null, $category = null, $page = 1, $perPage = 20, $sortby = 'date', $order = 'desc')
    {
        $finder = $this->finder('Andrew\ModeratorPanel:UserNote')
            ->with('User')
            ->with('Category')
            ->limitByPage($page, $perPage);

        if ($username) {
            $finder->where('NoteUser.username', $username);
        }

        if ($givenBy) {
            $finder->where('User.username', $givenBy);
        }
        if ($isPrivileged) {
            $finder->where('is_privileged', true);
        }

        if ($category) {
            $finder->where('Category.title', $category);
        }

        if (!$visitor->canViewPrivilegedUserNotes()) {
            $finder->where('is_privileged', false);
        }

        // Fetch all categories and filter them based on usability by the visitor
        $categoryFinder = \XF::finder('Andrew\ModeratorPanel:UserNoteCategory');
        $categories = $categoryFinder->fetch();

        $usableCategoryIds = [];
        foreach ($categories as $category) {
            if ($category->isUsableByUser($visitor)) {
                $usableCategoryIds[] = $category->note_category_id;
            }
        }

        if (!empty($usableCategoryIds)) {
            $finder->whereOr([
                ['note_category_id', 0],
                ['note_category_id', $usableCategoryIds]
            ]);
        } else {
            // If no usable categories, only include notes with no category
            $finder->where('note_category_id', 0);
        }

        switch ($sortby) {
            case 'given_to':
                $finder->order('NoteUser.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'created_by':
                $finder->order('User.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'date':
            default:
                $finder->order('create_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
        }

        return $finder;
    }
}
