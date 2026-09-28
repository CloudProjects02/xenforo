<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Move
 *
 * @package DBTech\Shop\Service\Item
 */
class Move extends \XF\Service\AbstractService
{
	/** @var \DBTech\Shop\Entity\Item */
	protected $item;
	
	/** @var bool */
	protected $alert = false;

	/** @var string */
	protected $alertReason = '';
	
	/** @var null */
	protected $prefixId;
	
	/** @var array */
	protected $extraSetup = [];
	
	/**
	 * Move constructor.
	 *
	 * @param \XF\App $app
	 * @param Item $item
	 */
	public function __construct(\XF\App $app, Item $item)
	{
		parent::__construct($app);
		$this->item = $item;
	}
	
	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): Move
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}
	
	/**
	 * @param int|null $prefixId
	 */
	public function setPrefix(?int $prefixId)
	{
		$this->prefixId = $prefixId;
	}
	
	/**
	 * @param callable $extra
	 */
	public function addExtraSetup(callable $extra)
	{
		$this->extraSetup[] = $extra;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function move(\DBTech\Shop\Entity\Category $category): bool
	{
		$user = \XF::visitor();

		$item = $this->item;

		$moved = ($item->category_id != $category->category_id);

		foreach ($this->extraSetup AS $extra)
		{
			$extra($item, $category);
		}

		$item->category_id = $category->category_id;
		if ($this->prefixId !== null)
		{
			$item->prefix_id = $this->prefixId;
		}

		if (!$item->preSave())
		{
			throw new \XF\PrintableException($item->getErrors());
		}

		$db = $this->db();
		$db->beginTransaction();

		$item->save(true, false);

		$db->commit();

		if ($moved && $item->isVisible() && $this->alert && $item->user_id != $user->user_id)
		{
			/** @var \DBTech\Shop\Repository\Item $itemRepo */
			$itemRepo = $this->repository('DBTech\Shop:Item');
			$itemRepo->sendModeratorActionAlert($this->item, 'move', $this->alertReason);
		}
		
		// Enqueue permission rebuild
		$this->app->jobManager()->enqueueUnique('permissionRebuild', 'XF:PermissionRebuild');
		
		if ($this->app->get('app.classType') == 'Pub')
		{
			// Immediately rebuild permissions
			$this->app->jobManager()
				->runUnique('permissionRebuild', 2)
			;
		}

		return $moved;
	}
}