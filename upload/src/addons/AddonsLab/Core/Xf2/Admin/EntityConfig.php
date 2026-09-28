<?php

namespace AddonsLab\Core\Xf2\Admin;

use AddonsLab\Core\Xf2\CrudEntityInterface;
use XF\Mvc\Entity\Entity;
use XF\Util\Arr;

abstract class EntityConfig
{
    /**
     * @var CrudEntityInterface
     */
    protected $item;

    /**
     * @var Entity
     */
    protected $parentItem;

    protected $phraseOverride = [];

    protected $linkParams = [];

    /**
     * @return string
     * The prefix for all product classes, e.g. AddonsLab\AddonName
     */
    abstract public function getAddonNamespace();

    /**
     * @return string
     * Short name of the entity to edit, e.g. TestItem etc.
     */
    abstract public function getEntityName();

    /**
     * @return string
     * Entity name in lower case, used to generate the names of templates, e.g. test_item
     * This does not have to be exactly the same name as the entity, it can be
     * anything specific to the current admin page if you are having multiple admin pages listing the same entity
     */
    abstract public function get_entity_name();

    /**
     * @return string
     * Lower-case prefix for all templates (usually common for the product), e.g. addon_id
     */
    abstract public function getPrefix();

    /**
     * @return string
     * The name of primary key of the entity, e.g. test_item_id
     */
    abstract public function getPrimaryKeyName();

    /**
     * @return string
     * The prefix used for all routes, e.g. addon/test-item. No trailing slash is needed.
     */
    abstract public function getRoutePrefix();

    /**
     * @return string
     * Id of Admin Permission to protected the page
     */
    abstract public function getAdminPermissionId();

    /**
     * @return Form
     * The configuration of the form used in Edit page
     */
    abstract public function getEditForm();

    /**
     * @return bool If true, the entity should have "active" column and a toggler will be shown to activate/deactivate the item
     */
    abstract public function hasActiveToggle();

    /**
     * @return bool If true, Controls popup will be shown with links from @see getControlPopupLinks method
     */
    abstract public function hasControlPopup();

    /**
     * @return string
     * The word to use instead of "item" in phrases, e.g. "test item"
     */
    abstract public function getItemNameLowerCase();

    /**
     * @return string
     * E.g. "Test Item"
     */
    abstract public function getItemNameUpperCase();

    /**
     * @return string
     * The word to use instead of "items" in phrases, e.g. "test items"
     */
    abstract public function getItemPluralNameLowerCase();

    /**
     * @return string
     * E.g. "Test Items"
     */
    abstract public function getItemPluralNameUpperCase();

    protected static $instances = [];

    /**
     * @param false $reset
     * @return static
     */
    public static function getInstance($reset = false)
    {
        $className = static::class;

        if (isset(self::$instances[$className]) && $reset === false)
        {
            return self::$instances[$className];
        }

        self::$instances[$className] = new static();

        return self::$instances[$className];
    }

    public function __construct()
    {

    }


    public function setItem(CrudEntityInterface $item)
    {
        $this->item = $item;
    }

    public function setLinkParams(array $linkParams)
    {
        $this->linkParams = $linkParams;
    }

    public function setParentItem(Entity $item)
    {
        $this->parentItem = $item;
    }

    /**
     * Used in templates only, due to limitation on method names callable in a template
     * @param CrudEntityInterface $item
     */
    public function isSetActiveItem(CrudEntityInterface $item)
    {
        $this->setItem($item);
    }

    /**
     * @param CrudEntityInterface $item
     * @return bool
     */
    public function canActivateDeactivate(CrudEntityInterface $item)
    {
        return true;
    }

    public function getItemBreadcrumb(CrudEntityInterface $item)
    {
        return [
            'value' => $item->getItemTitle(),
            'href' => $this->buildLink($this->getRoutePrefix(), $item) . '#__' . $item->getItemPrimaryKey(),
        ];
    }

    /**
     * @return bool If true, a quick filter control will be shown above the list
     */
    public function hasQuickFilter()
    {
        return true;
    }

    /**
     * @return array
     * Array of entities to fetch with the main entity by default
     */
    public function getDefaultWith()
    {
        return array();
    }

    /**
     * @return array
     * The entities to fetch with the main entity when fetching list data
     */
    public function getListWith()
    {
        return array();
    }

    /**
     * @return bool
     * Add button will be shown on top of the page and Add item page will be available
     */
    public function hasAddPage()
    {
        return true;
    }

    /**
     * @return bool
     * Item in the list will be clickable and user will be taken to the edit form
     */
    public function hasViewPage()
    {
        return true;
    }

    /**
     * @return bool
     * Save button will be shown, otherwise, Go Back button
     */
    public function hasSavePage()
    {
        return true;
    }

    /**
     * @return bool
     * Delete button will be shown
     */
    public function hasDeletePage()
    {
        return true;
    }

    /**
     * @return bool If true, each item will be opened in an overlay instead of a separate page
     */
    public function hasOverlay()
    {
        return true;
    }

    public function getEditOverlayCache()
    {
        return true;
    }

    /**
     * @return array
     * Array with links to be shown in the popup. Each link should be an array with attributes as defined below
     */
    public function getControlPopupLinks()
    {
        return array(
            array(
                'route' => '/change/to/route',
                'args' => array(),
                'attr' => 'data-test="change"',
                'text' => 'Override @getControlPopupLinks method',
            ),
        );
    }

    /**
     * @param CrudEntityInterface $item
     * @return array=[
     *     ['class'=>'dataList-cell--action', 'link'=>'', 'text'=>'']
     * ]
     */
    public function getListDataCells(CrudEntityInterface $item)
    {
        return [];
    }

    /**
     * Any additional attributes for the Add item button on the top
     * @return bool
     */
    public function getUseAddOverlay()
    {
        return false;
    }

    /**
     * @return bool|FilterForm
     * The form to build page filter. Will be shown as a separate form above the main list
     */
    public function getFilterForm()
    {
        return false;
    }

    /**
     * @param string $entity
     * @return string
     * E.g. AddonsLab\AddonName:TestItem
     */
    public function getFullEntityName($entity = '')
    {
        if (!$entity)
        {
            $entity = $this->getEntityName();
        }

        if (strpos($entity, ':') !== false)
        {
            return $entity;
        }

        return $this->getAddonNamespace() . ':' . $entity;
    }

    /**
     * The name of entity used to build the list. By default the same entity as the one used in forms
     * @return string
     */
    public function getListEntityName()
    {
        return $this->getEntityName();
    }

    /**
     * @param $action
     * @return string
     * E.g. AddonsLab\AddonName:TestItem\Edit
     */
    public function getViewName($action)
    {
        return $this->getAddonNamespace() . ':' . $this->getEntityName() . '\\' . $action;
    }

    /**
     * @param $postfix
     * @return string
     * E.g. addon_id_test_item_edit
     */
    public function getTemplateName($postfix)
    {
        return $this->getPrefix() . '_' . $this->get_entity_name() . '_' . strtolower($postfix);
    }

    public function getPhraseName($postfix)
    {
        return $this->getPrefix() . '_' . $this->get_entity_name() . '_' . $postfix;
    }

    public function getPhrase($postfix)
    {
        $phraseName = $this->getPhraseName($postfix);
        if (isset($this->phraseOverride[$phraseName]))
        {
            return $this->phraseOverride[$phraseName];
        }

        return \XF::phrase($phraseName);
    }

    public function setPhraseOverride($phraseOverride)
    {
        $this->phraseOverride = Arr::mapMerge($this->phraseOverride, $phraseOverride);
    }

    /**
     * @return array = [
     *     ['route'=>'export', 'icon'=>'', 'overlay'=>true, 'text'=>\XF::phrase('export')]
     * ]
     */
    public function getPageActions()
    {
        return [];
    }

    protected function buildLink($link, $data = null, array $parameters = [], $hash = null)
    {
        return \XF::app()->router('admin')->buildLink($link, $data, $parameters, $hash);
    }

    /**
     * @return string[]
     */
    public function _getEmptyDataCell(array $linkData = []): array
    {
        return [
            'class' => $linkData['class'] ?? 'dataList-cell--action',
            'link' => $linkData['link'] ?? 'javascript:void()',
            'text' => $linkData['text'] ?? '',
            'onClick' => $linkData['onClick'] ?? '',
            'followRedirects' => $linkData['followRedirects'] ?? '',
            'target' => $linkData['target'] ?? '_self',
            'additionalAttributes' => $linkData['additionalAttributes'] ?? '',
        ];
    }

    public function getFormJsInit()
    {
        return '';
    }

    public function getListRowClass(CrudEntityInterface $item)
    {
        return '';
    }

    /**
     * @return string Anything to be injected on top of the list template
     */
    public function getListHeaderSnippet()
    {
        return '';
    }

    public function getCustomListingLink(CrudEntityInterface $item, $linkParams = [])
    {
        return 'javascript:void()';
    }

    public function canCreate(Entity $parentItem = null)
    {
        return true;
    }

    public function getSubmitSticky()
    {
        return true;
    }
}
