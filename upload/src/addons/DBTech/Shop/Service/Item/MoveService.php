<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\ItemRepository;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class MoveService extends AbstractService
{
	protected Item $item;
	protected bool $alert = false;
	protected string $alertReason = '';
	protected ?int $prefixId = null;
	protected array $extraSetup = [];

	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
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
	public function setSendAlert(bool $alert, ?string $reason = null): MoveService
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
	public function setPrefix(?int $prefixId): void
	{
		$this->prefixId = $prefixId;
	}

	/**
	 * @param callable $extra
	 */
	public function addExtraSetup(callable $extra): void
	{
		$this->extraSetup[] = $extra;
	}

	/**
	 * @param Category $category
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function move(Category $category): bool
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
			throw new PrintableException($item->getErrors());
		}

		$db = $this->db();
		$db->beginTransaction();

		$item->save(true, false);

		$db->commit();

		if ($moved && $item->isVisible() && $this->alert && $item->user_id != $user->user_id)
		{
			$itemRepo = \XF::app()->repository(ItemRepository::class);
			$itemRepo->sendModeratorActionAlert($this->item, 'move', $this->alertReason);
		}

		// Enqueue permission rebuild
		\XF::app()->jobManager()->enqueueUnique('permissionRebuild', 'XF:PermissionRebuild');

		if (\XF::app()->get('app.classType') == 'Pub')
		{
			// Immediately rebuild permissions
			\XF::app()->jobManager()
				->runUnique('permissionRebuild', 2)
			;
		}

		return $moved;
	}
}