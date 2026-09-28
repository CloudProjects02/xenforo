<?php

namespace AddonsLab\Core\Xf2\ControllerTrait;

use AddonsLab\Core\Xf2\Admin\EntityConfig;
use AddonsLab\Core\Xf2\Admin\Field\AbstractRow;
use AddonsLab\Core\Xf2\Admin\FilterFormParams;
use AddonsLab\Core\Xf2\CrudEntityInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\View;

trait CrudControllerTrait
{
    public function actionIndex(ParameterBag $params)
    {
        return $this->rerouteController(static::class, 'list', $params);
    }

    public function actionApplyFilter(ParameterBag $params)
    {
        $form_filter = $this->_getFilterFormInput();

        $listParams = $this->_getListParams();

        if (!empty($form_filter))
        {
            $listParams['linkParams']['form_filter'] = $form_filter;
        }

        return $this->redirect($this->buildLink($this->config()->getRoutePrefix(), null, $listParams['linkParams']));
    }

    protected function _getFilterFormInput()
    {
        $form_filter = $this->filter('form_filter', 'array');

        $form_filter = array_filter($form_filter, function ($filter)
        {
            if (
                (is_scalar($filter) && strlen($filter) === 0)
                ||
                (is_array($filter) && empty($filter))
            )
            {
                return false;
            }

            return true;
        });

        return $form_filter;
    }

    protected function _getListParams()
    {
        $breadcrumbs = [];
        $parentBreadcrumb = $this->_getParentBreadcrumb();
        if ($parentBreadcrumb)
        {
            $breadcrumbs[] = $parentBreadcrumb;
        }

        return [
            'linkParams' => $this->_getLinkParams(),
            'breadcrumbs' => $breadcrumbs,
            'parentEntity' => $this->_getParentEntity(),
            'phrases' => [
                'manage' => $this->config()->getPhrase('manage'),
                'page_description' => '',
                'add' => $this->config()->getPhrase('add'),
                'no_items_have_been_created_yet' => $this->config()->getPhrase('no_items_have_been_created_yet'),
                'no_items_matching_filter_criteria' => $this->config()->getPhrase('no_items_matching_filter_criteria'),
                'filter' => \XF::phrase('filter'),
            ]
        ];
    }

    public function actionList(ParameterBag $params)
    {
        [$order, $direction] = $this->_filterOrderParams();

        $page = $this->filterPage();
        $perPage = $this->_getPerPage();

        $showingAll = $this->filter('all', 'bool');

        if ($showingAll)
        {
            $page = 1;
            $perPage = 5000;
        }

        $finder = $this->getFinder();

        $finder->limitByPage($page, $perPage);

        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);

        $form_filter = $this->_getFilterFormInput();

        $this->_filterFinder($finder, $filter);
        $this->_applyFilterForm($finder, $form_filter);
        $this->_orderFinder($finder, $form_filter['sort_order'] ?? $order, $form_filter['sort_direction'] ?? $direction);

        $total = $finder->total();
        $items = $finder->fetch();

        $this->assertValidPage($page, $perPage, $total, $this->config()->getRoutePrefix(), $this->_getParentEntity());

        if (
            !strlen($filter['text'])
            && $total == 1 && ($item = $items->first())
            && $this->config()->hasViewPage()
            && $this->_redirectOnSingleItem()
        )
        {
            return $this->redirect($this->buildLink($this->config()->getRoutePrefix() . '/edit', $item));
        }

        $items = $this->_prepareItems($items);

        // give extending controllers ability to do conversion/preparation of items
        $items = array_map([$this, '_convertEntity'], $items->toArray());

        $viewParams = [
            'items' => $items,

            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,

            'showingAll' => $showingAll,
            'showAll' => (!$showingAll && $total <= 5000),
            'filter' => $filter['text'],
            'order' => $order,
            'direction' => $direction,
        ];

        $viewParams += $this->_getListParams();

        $viewParams['form_filter'] = $this->_prepareFormFilterParams($form_filter);

        $this->config()->setLinkParams($viewParams['linkParams']);

        return $this->view(
            $this->config()->getViewName('List'),
            $this->config()->getTemplateName('list'),
            $viewParams
        );
    }

    /**
     * @return int
     */
    protected function _getPerPage()
    {
        return 20;
    }

    /**
     * @return Finder
     */
    public function getFinder()
    {
        $finder = \XF::finder($this->config()->getFullEntityName(
            $this->config()->getListEntityName()
        ))->with(
            $this->config()->getListWith()
        );

        return $finder;
    }

    /**
     * @param Finder $finder
     * @param array $filter
     * Should setup the finder object based on filter submitted
     */
    protected function _filterFinder(Finder $finder, array $filter)
    {
        if ($this->_getParentEntity())
        {
            $this->_filterFinderForParent($finder, $filter);
        }
    }

    protected function _filterFinderForParent(Finder $finder, array $filter)
    {
        throw new \RuntimeException('Please override the method _filterFinderForParent to apply filters based on parent entity.');
    }

    protected function _applyFilterForm(Finder $finder, array &$form_filter)
    {

    }

    protected function _orderFinder(Finder $finder, $order, $direction)
    {
        if ($order)
        {
            try
            {
                $finder->resolveFieldToTableAndColumn($order);
            }
            catch (\LogicException $e)
            {
                if (\XF::$developmentMode)
                {
                    throw $e;
                }

                // Invalid relation is provided, by users in the URL
                return;
            }

            $finder->order($order, $direction);
        }
    }

    protected function _redirectOnSingleItem()
    {
        return false;
    }

    /**
     * @param array $form_filter
     * @return FilterFormParams
     */
    protected function _prepareFormFilterParams(array $form_filter)
    {
        $form = $this->config()->getFilterForm();
        $params = new FilterFormParams($form_filter);
        if ($form)
        {
            $params->setForm($form);
        }
        return $params;
    }

    public function actionAdd(ParameterBag $params)
    {
        $item = $this->_getDefaultItem();
        $this->config()->setItem($item);

        $this->_assertCanAddItem($item);

        return $this->view(
            $this->config()->getViewName('Add'),
            $this->config()->getTemplateName('edit'),
            [
                'item' => $item,
                'context' => $this->_getContextParams(),
                'form' => $this->config()->getEditForm(),
                'redirect' => $this->filter('redirect', 'str', 'on'),
                'linkParams' => $this->_getLinkParams(),
            ] + $this->_getAddEditParams($item)
        );
    }

    /**
     * @return Entity|CrudEntityInterface
     */
    protected function _getDefaultItem()
    {
        /** @var CrudEntityInterface $item */
        $item = \XF::em()->create($this->config()->getFullEntityName());

        if ($this->_getParentEntity())
        {
            $this->_setupItemDefaultFromParent($item);
        }

        return $item;
    }

    /**
     * The method is meant to make sure, that a newly created child item has all information it needs about its parent item
     * e.g. automatically gets parent_id set in it, instead of filtering from input
     * @param CrudEntityInterface $item
     */
    protected function _setupItemDefaultFromParent(CrudEntityInterface $item)
    {
        throw new \LogicException('Please override the method _setupItemDefaultFromParent');
    }

    protected function _getContextParams()
    {
        return [];
    }

    protected function _filterFromContext($key, $type = null)
    {
        $context = $this->filter('_context', 'json-array', []);
        if (is_array($key))
        {
            return $this->filterArray($context, $key);
        }
        $context = $this->filterArray($context, [$key => $type]);
        return $context[$key] ?? null;
    }

    /**
     * @param CrudEntityInterface|Entity $item
     * @return array
     */
    protected function _getAddEditParams(CrudEntityInterface $item)
    {
        $form = $this->config()->getEditForm();
        $attachmentData = [];
        $attachments = [];

        /** @var \XF\Repository\Attachment $attachmentRepo */
        $attachmentRepo = $this->repository('XF:Attachment');

        foreach ($form->getFields() as $field)
        {
            if ($content_type = $field->getFormatParam('attachment_content_type'))
            {
                $attachmentData[$field->getId()] = $attachmentRepo->getEditorData($content_type, $item);
                $attachments[$field->getId()] = $item->exists() ? $attachmentRepo->findAttachmentsByContent(
                    $content_type,
                    $item->getItemPrimaryKey()
                )->fetch() : [];
            }
        }

        return [
            'phrases' => [
                'create_new' => $this->config()->getPhrase('create_new'),
                'edit' => $this->config()->getPhrase('edit'),
            ],
            'breadcrumbs' => $this->_getItemBreadcrumbs($item),
            'attachmentData' => $attachmentData,
            'attachments' => $attachments,
        ];
    }

    public function actionEdit(ParameterBag $params)
    {
        $item = $this->_assertItemFromParams($params);

        if (!$item)
        {
            return $this->notFound();
        }

        $this->config()->setItem($item);

        return $this->view(
            $this->config()->getViewName('Edit'),
            $this->config()->getTemplateName('edit'),
            [
                'item' => $item,
                'context' => $this->_getContextParams(),
                'form' => $this->config()->getEditForm(),
                'redirect' => $this->filter('redirect', 'str', 'on'),
                'linkParams' => $this->_getLinkParams(),
            ] + $this->_getAddEditParams($item)
        );
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return Entity|CrudEntityInterface
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function _assertItemExists($id, $with = null, $phraseKey = null)
    {
        $with = $this->_getEffectiveWith($with);
        return $this->assertRecordExists($this->config()->getFullEntityName(), $id, $with, $phraseKey);
    }

    protected function _getEffectiveWith($with = null)
    {
        if ($with === null)
        {
            $with = [];
        }

        $with = array_merge($with, $this->config()->getDefaultWith());

        return $with;
    }

    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($itemId = $params->get($this->config()->getPrimaryKeyName()))
        {
            $item = $this->_assertItemExists($itemId);
            $this->_assertCanEditItem($item);
        }
        else
        {
            $item = $this->_getDefaultItem();
            $this->_assertCanAddItem($item);
        }

        $this->config()->setItem($item);

        $this->_itemSaveProcess($item)->run();

        return $this->redirect(
            $this->buildLink(
                $this->config()->getRoutePrefix(),
                $this->_getParentEntity(),
                $this->_getRedirectParams($item)
            )
        );
    }

    /**
     * @param Entity $entity
     * @throws \XF\Mvc\Reply\Exception
     * Entity-specific permissions can be implemented by child classes
     */
    protected function _assertCanEditItem(CrudEntityInterface $entity)
    {
        if (!$this->config()->hasSavePage())
        {
            throw $this->exception(
                $this->noPermission()
            );
        }
    }

    /**
     * @param Entity $entity
     */
    protected function _assertCanAddItem(CrudEntityInterface $entity)
    {
        if (!$this->config()->hasAddPage() || !$this->config()->canCreate($this->_getParentEntity()))
        {
            throw $this->exception(
                $this->noPermission()
            );
        }
    }

    /**
     * @param CrudEntityInterface|Entity $entity
     * @return \XF\Mvc\FormAction
     */
    protected function _itemSaveProcess(CrudEntityInterface $entity)
    {
        $form = $this->formAction();

        $inputData = $this->_getInputData();

        $inputData = $this->_prepareInputDataForEntity($entity, $inputData);

        $entityName = $this->config()->getEntityName();
        $entityData = isset($inputData[$entityName]) ? $inputData[$entityName] : [];

        $form->setupEntityInput($entity, $entityData)
            ->validateEntity($entity)
            ->saveEntity($entity);

        foreach ($inputData as $relationName => $input)
        {
            if ($relationName === $this->config()->getEntityName())
            {
                continue;
            }

            try
            {

                $relation = $entity->getRelationOrDefault($relationName, true);
            }
            catch (\InvalidArgumentException $ex)
            {
                // No such relation, the array could be a custom input name
                // so let the extending classes to handle it
                $this->_setupCustomInput($form, $entity, $relationName, $input);
                continue;
            }

            $form->setupEntityInput($relation, $input);
        }

        $form->complete(function () use ($entity)
        {
            /** @var \XF\Service\Attachment\Preparer $inserter */
            $inserter = $this->service('XF:Attachment\Preparer');

            foreach ($this->config()->getEditForm()->getFields() as $field)
            {
                if ($content_type = $field->getFormatParam('attachment_content_type'))
                {
                    $associated = $inserter->associateAttachmentsWithContent(
                        $this->filter('attachment_hash_' . $field->getId(), 'str'),
                        $content_type,
                        $entity->getItemPrimaryKey()
                    );

                    if ($associated)
                    {
                        $entity->fastUpdate(
                            'attach_count_' . $field->getId(),
                            $entity->get('attach_count_' . $field->getId()) + $associated
                        );
                    }
                }
            }
        });

        return $form;
    }

    /**
     * @return array
     */
    protected function _getInputData()
    {
        $fields = $this->config()->getEditForm()->getFields();

        $fields = array_filter(
            $fields,
            function (AbstractRow $field)
            {
                return !in_array($field->getType(), [
                    AbstractRow::TYPE_INFO,
                    AbstractRow::TYPE_CUSTOM,
                ], true);
            }
        );

        $inputData = [];

        /** @var AbstractRow $field */
        foreach ($fields as $field)
        {
            $fieldId = $field->getId();
            $entityName = $field->getRelationName();
            if (!$entityName)
            {
                $entityName = $this->config()->getEntityName();
            }

            $entityClass = $this->config()->getFullEntityName($entityName);

            $fullEntityClass = \XF::app()->em()->getEntityClassName($entityClass);
            $inputStructure = $fullEntityClass::getInputStructure();
            $inputData[$field->getInputName() ?: $entityName][$fieldId] = $field->getInputType() ?: $inputStructure[$fieldId];
        }

        foreach ($inputData as $entityName => $entityInfo)
        {
            $entityData = $this->filter($entityName, 'array');
            array_walk($entityInfo, function (&$fieldType, $fieldId) use ($entityName, $entityData)
            {
                if ($fieldType === 'editor')
                {
                    $fieldType = $this->plugin('XF:Editor')->fromInput("{$entityName}.$fieldId");
                }
                else
                {
                    $filedValue = isset($entityData[$fieldId]) ? $entityData[$fieldId] : null;
                    $fieldType = $this->request()->getInputFilterer()->filter($filedValue, $fieldType);
                }
            });

            $inputData[$entityName] = $entityInfo;
        }

        // This was a wrong approach to take, as there are many keys in input
        // that we might not want to filter from the input
        // fields missing from input should be filtered manually
        /*foreach ($entities as $entityName => $inputStructure)
        {
            // Remove the primary key from the input structure
            unset($inputStructure[$this->config()->getPrimaryKeyName()]);

            // If there is a parent entity, remove the primary key of the parent
            // as parent information should be setup using the method _setupItemDefaultFromParent()
            if ($this->_getParentConfig())
            {
                unset($inputStructure[$this->_getParentConfig()->getPrimaryKeyName()]);
            }

            $entityData = $this->filter($entityName, 'array');

            foreach ($inputStructure as $inputName => $inputType)
            {
                if (!isset($inputData[$entityName][$inputName]))
                {
                    // Input was missing, could be checkbox with no value checked, just populate with default value
                    $defaultValue = $this->request()
                        ->getInputFilterer()
                        ->filter(
                            $entityData[$inputName] ?? null,
                            $inputType
                        );
                    $inputData[$entityName][$inputName] = $defaultValue;
                }
            }
        }*/

        return $inputData;
    }

    /**
     * Allow extending controllers to customize the input data based on entity,
     * e.g. remove the data which should not be editable
     * @param CrudEntityInterface|Entity $entity
     * @param array $inputData
     * @return array
     */
    protected function _prepareInputDataForEntity(CrudEntityInterface $entity, array $inputData)
    {
        return $inputData;
    }

    protected function _getRedirectParams(CrudEntityInterface $item)
    {
        return ['last_item_id' => $item->getItemPrimaryKey()] + $this->_getLinkParams();
    }

    public function actionToggle(ParameterBag $params)
    {
        $active = $this->filter('active', 'array');

        $config = $this->config();
        foreach ($active as $itemId => $isActive)
        {
            $isActive = (bool)$isActive;

            $item = $this->_assertItemExists($itemId);
            $this->_assertCanEditItem($item);

            if (!$config->canActivateDeactivate($item))
            {
                continue;
            }

            if ($item->active != $isActive)
            {
                $this->_assertCanToggleActiveStatus($item);
            }

            $item->active = $isActive;
            $item->saveIfChanged();
        }

        return $this->redirect(
            $this->buildLink(
                $this->config()->getRoutePrefix(),
                $this->_getParentEntity(),
                $this->_getLinkParams()
            )
        );
    }

    /**
     * @param CrudEntityInterface $item
     */
    protected function _assertCanToggleActiveStatus(CrudEntityInterface $item)
    {

    }

    public function actionDelete(ParameterBag $params)
    {
        $item = $this->_assertItemFromParams($params);

        $this->_assertCanDeleteItem($item);

        if (!$item->preDelete())
        {
            return $this->error($item->getErrors());
        }

        if ($this->isPost())
        {
            $this->_setupDelete($item);
            $item->delete();

            return $this->redirect(
                $this->getDynamicRedirectIfNot(
                    $this->buildLink($this->config()->getRoutePrefix() . '/edit', $item),
                    $this->buildLink(
                        $this->config()->getRoutePrefix(),
                        $this->_getParentEntity()
                    )
                )
            );
        }
        else
        {
            return $this->view(
                $this->config()->getViewName('Delete'),
                $this->config()->getTemplateName('delete'),
                [
                    'item' => $item,
                    'context' => $this->_getContextParams(),
                    'form' => $this->config()->getEditForm(),
                    'redirect' => $this->filter('redirect', 'str', 'on'),
                ] + $this->_getAddEditParams($item)
            );
        }
    }

    protected function _assertCanDeleteItem(CrudEntityInterface $entity)
    {
        if (!$this->config()->hasDeletePage())
        {
            throw $this->exception(
                $this->noPermission()
            );
        }
    }

    /**
     * @param CrudEntityInterface|Entity $item
     * Child controls can override this to customize deletion
     */
    protected function _setupDelete(CrudEntityInterface $item)
    {

    }

    /**
     * @return Entity|null
     */
    protected function _getParentEntity()
    {
        return null;
    }

    /**
     * @return EntityConfig|null
     */
    protected function _getParentConfig()
    {
        return null;
    }


    protected function postDispatchController($action, ParameterBag $params, AbstractReply &$reply)
    {
        parent::postDispatchController($action, $params, $reply);

        if ($reply instanceof View)
        {
            $reply->setParams($this->_mergeDefaultParams($reply->getParams(), $action));
        }

        $reply->setJsonParams($this->_getJsonParams($action, $params, $reply));
    }

    protected function _mergeDefaultParams(array $params, $action)
    {
        $params += [
            'config' => $this->config(),
            'routePrefix' => $this->config()->getRoutePrefix(),
            'filterForm' => $this->config()->getFilterForm(),
            'parentEntity' => $this->_getParentEntity(),
            'parentConfig' => $this->_getParentConfig(),

        ];

        return $params;
    }

    protected function _getJsonParams()
    {
        return [];
    }

    /**
     * @param Entity $entity
     * @return CrudEntityInterface|Entity
     */
    protected function _convertEntity(Entity $entity)
    {
        return $entity;
    }

    protected function _prepareItems(\XF\Mvc\Entity\ArrayCollection $items)
    {
        return $items;
    }

    /**
     * @return array|null
     */
    protected function _getParentBreadcrumb()
    {
        $parentEntity = $this->_getParentEntity();
        $parentConfig = $this->_getParentConfig();

        if (
            $parentEntity && $parentEntity instanceof CrudEntityInterface
            && $parentConfig
        )
        {
            return $this->_getParentConfig()->getItemBreadcrumb($parentEntity);
        }

        return null;
    }

    protected function _getItemBreadcrumbs(CrudEntityInterface $item)
    {
        $breadcrumbs = [];

        if ($parentBreadcrumb = $this->_getParentBreadcrumb())
        {
            $breadcrumbs[] = $parentBreadcrumb;
        }

        if ($parentEntity = $this->_getParentEntity())
        {
            // Add a link to the list page as well
            $breadcrumbs[] = [
                'value' => $this->config()->getPhrase('manage'),
                'href' => $this->buildLink($this->config()->getRoutePrefix(), $parentEntity),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param ParameterBag $params
     * @return CrudEntityInterface|Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function _assertItemFromParams(ParameterBag $params)
    {
        $itemId = $params->get($this->config()->getPrimaryKeyName());

        return $this->_assertItemExists($itemId);
    }

    protected function _setupCustomInput(\XF\Mvc\FormAction $form, CrudEntityInterface $entity, string $inputName, array $input)
    {
        throw new \RuntimeException("Custom input name $inputName is not handled properly.");
    }

    protected function _filterOrderParams()
    {
        $order = $this->filter('order', 'str');
        $direction = $this->filter('direction', 'str');
        return [$order, $direction];
    }

    /**
     * @return array
     */
    protected function _getLinkParams(): array
    {
        $filter = $this->filter('_xfFilter', [
            'text' => 'str',
            'prefix' => 'bool'
        ]);

        $linkParams = [];
        $form_filter = $this->_getFilterFormInput();
        if (!empty($form_filter))
        {
            $linkParams['form_filter'] = $form_filter;
        }
        if ($filter['text'])
        {
            $linkParams['_xfFilter'] = $filter;
        }
        return $linkParams;
    }
}