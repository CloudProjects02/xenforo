<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\ItemType\ConfigurableInterface;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class Purchase
 * @package DBTech\Shop\Admin\Controller
 */
class Purchase extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechShop');
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$criteria = $this->filter('criteria', 'array');
		$order = $this->filter('order', 'str');
		$direction = $this->filter('direction', 'str');
		
		$page = $this->filterPage();
		$perPage = 20;
		
		/** @var \DBTech\Shop\Searcher\Purchase $searcher */
		$searcher = $this->searcher('DBTech\Shop:Purchase', $criteria);
		
		if ($order && !$direction)
		{
			$direction = $searcher->getRecommendedOrderDirection($order);
		}
		
		$searcher->setOrder($order, $direction);

		/** @var \DBTech\Shop\Finder\Purchase $finder */
		$finder = $searcher->getFinder();
		$finder->with(['User', 'Buyer', 'Item']);
		$finder->limitByPage($page, $perPage);

		$filter = $this->filter('_xfFilter', [
			'text' => 'str',
			'prefix' => 'bool'
		]);
		if ($filter['text'] !== '')
		{
			$finder->searchText($filter['text'], false, $filter['prefix']);
		}
		
		$total = $finder->total();
		$entries = $finder->fetch();
		
		$viewParams = [
			'entries' => $entries,
			
			'total' => $total,
			'page' => $page,
			'perPage' => $perPage,
			
			'criteria' => $searcher->getFilteredCriteria(),
			// 'filter' => $filter['text'],
			'sortOptions' => $searcher->getOrderOptions(),
			'order' => $order,
			'direction' => $direction
		
		];
		return $this->view('DBTech\Shop:Log\Purchase\Listing', 'dbtech_shop_purchase_list', $viewParams);
	}

	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionSearch(): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = $this->getSearcherParams();

		return $this->view('DBTech\Shop:Log\Purchase\Search', 'dbtech_shop_purchase_search', $viewParams);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 * @return \XF\Mvc\Reply\View
	 */
	protected function purchaseAddEdit(\DBTech\Shop\Entity\Purchase $purchase): \XF\Mvc\Reply\AbstractReply
	{
		$viewParams = [
			'purchase' => $purchase,
		];
		return $this->view('DBTech\Shop:Purchase\Edit', 'dbtech_shop_purchase_edit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Purchase $purchase */
		$purchase = $this->assertPurchaseExists($params->purchase_id, 'Item');
		return $this->purchaseAddEdit($purchase);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd()
	{
		$itemRepo = $this->getItemRepo();
		if (!$itemRepo->findItemsForList()->total())
		{
			throw $this->exception($this->error(\XF::phrase('dbtech_shop_please_create_at_least_one_item_before_continuing')));
		}
		
		$itemName = $this->filter('item', 'str');
		$itemId = $this->filter('item_id', 'uint');
		
		if ($itemName)
		{
			/** @var \DBTech\Shop\Finder\Item $finder */
			$finder = $this->finder('DBTech\Shop:Item');
			$finder->searchText($itemName, false, false, true);
			
			/** @var \DBTech\Shop\Entity\Item $item */
			$item = $finder->fetchOne();
			if ($item)
			{
				return $this->redirect($this->buildLink('dbtech-shop/purchases/add', null, ['item_id' => $item->item_id]));
			}
		}
		elseif ($itemId)
		{
			$item = $this->em()->find('DBTech\Shop:Item', $itemId);
			if ($item)
			{
				/** @var \DBTech\Shop\Entity\Purchase $purchase */
				$purchase = $this->em()->create('DBTech\Shop:Purchase');
				$purchase->item_id = $item->item_id;
				
				$purchase->hydrateRelation('Item', $item);
				
				return $this->purchaseAddEdit($purchase);
			}
		}
		
		return $this->view('DBTech\Shop:Purchase\AddChooser', 'dbtech_shop_purchase_add_chooser');
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Purchase $purchase
	 *
	 * @return FormAction
	 * @throws \Exception
	 */
	protected function purchaseSaveProcess(\DBTech\Shop\Entity\Purchase $purchase): FormAction
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'message' => 'str',
			
			'active' => 'bool',
			'hidden' => 'bool',
			'gifted' => 'bool',
			'traded' => 'bool',
		]);
		$itemId = $this->filter('item_id', 'uint');
		$username = $this->filter('username', 'str');
		$buyerUsername = $this->filter('buyer_username', 'str');

		if (!$purchase->exists())
		{
			/** @var \DBTech\Shop\Entity\Item $item */
			$item = $this->em()->find('DBTech\Shop:Item', $itemId);
			if ($item)
			{
				$purchase->item_id = $item->item_id;
				$purchase->hydrateRelation('Item', $item);
			}
			else
			{
				$form->logError(\XF::phrase('dbtech_shop_invalid_item'));
			}
			
			/** @var \XF\Entity\User $user */
			$user = $this->finder('XF:User')
				->where('username', $username)
				->fetchOne();
			if ($user)
			{
				$purchase->user_id = $user->user_id;
				$purchase->hydrateRelation('User', $user);

				$purchase->buyer_user_id = $user->user_id;
				$purchase->buyer_username = $user->username;
				$purchase->hydrateRelation('Buyer', $user);
			}
			else
			{
				$form->logError(\XF::phrase('requested_user_not_found'));
			}
		}

		if ($buyerUsername)
		{
			/** @var \XF\Entity\User $buyer */
			$buyer = $this->finder('XF:User')
				->where('username', $buyerUsername)
				->fetchOne()
			;
			if ($buyer)
			{
				$purchase->buyer_user_id = $buyer->user_id;
				$purchase->buyer_username = $buyer->username;
				$purchase->hydrateRelation('Buyer', $buyer);
			}
			else
			{
				$form->logError(\XF::phrase('dbtech_shop_buyer_user_not_found'));
			}
		}
		else
		{
			$purchase->buyer_user_id = $purchase->user_id;
			$purchase->buyer_username = $purchase->User->username;
			$purchase->hydrateRelation('Buyer', $purchase->User);
		}
		
		$form->basicEntitySave($purchase, $input);
		
		$language = \XF::language();
		
		$purchaseDateInput = $this->filter([
			'purchase_date' => 'datetime',
			'purchase_time' => 'str'
		]);
		$form->setup(function () use ($purchaseDateInput, $purchase, $language)
		{
			$dateTime = new \DateTime('@' . $purchaseDateInput['purchase_date']);
			$dateTime->setTimezone($language->getTimeZone());
			
			if (!$purchaseDateInput['purchase_time'] || strpos($purchaseDateInput['purchase_time'], ':') === false)
			{
				// We didn't have a valid time string
				$hours = $language->date($purchase->dateline, 'H');
				$minutes = $language->date($purchase->dateline, 'i');
			}
			else
			{
				[$hours, $minutes] = explode(':', $purchaseDateInput['purchase_time']);
				
				// Sanitise hours and minutes to a maximum of 23:59
				$hours = min((int)$hours, 23);
				$minutes = min((int)$minutes, 59);
			}
			
			// Finally set it
			$dateTime->setTime($hours, $minutes);
			
			$purchase->dateline = $dateTime->getTimestamp();
		});
		
		if ($purchase->isInsert())
		{
			$lengthDateInput = $this->filter([
				'length_type' => 'str',
				'length_amount' => 'uint',
				'length_unit' => 'str',
			]);
			$form->setup(function () use ($lengthDateInput, $purchase)
			{
				if ($lengthDateInput['length_type'] == 'permanent')
				{
					$purchase->expiry_date = 0;
				}
				else
				{
					$purchase->expiry_date = strtotime('+' . $lengthDateInput['length_amount'] . ' ' . $lengthDateInput['length_unit'], $purchase->dateline);
				}
			});
		}
		else
		{
			$expiryDateInput = $this->filter([
				'length_type' => 'str',
				'expiry_date' => 'datetime',
				'expiry_time' => 'str'
			]);
			$form->setup(function () use ($expiryDateInput, $purchase, $language)
			{
				if ($expiryDateInput['length_type'] == 'permanent')
				{
					$purchase->expiry_date = 0;
				}
				else
				{
					$dateTime = new \DateTime('@' . $expiryDateInput['expiry_date']);
					$dateTime->setTimezone($language->getTimeZone());
					
					if (!$expiryDateInput['expiry_time'] || strpos($expiryDateInput['expiry_time'], ':') === false)
					{
						// We didn't have a valid time string
						$hours = $language->date($purchase->expiry_date, 'H');
						$minutes = $language->date($purchase->expiry_date, 'i');
					}
					else
					{
						[$hours, $minutes] = explode(':', $expiryDateInput['expiry_time']);
						
						// Sanitise hours and minutes to a maximum of 23:59
						$hours = min((int)$hours, 23);
						$minutes = min((int)$minutes, 59);
					}
					
					// Finally set it
					$dateTime->setTime($hours, $minutes);
					
					$purchase->expiry_date = $dateTime->getTimestamp();
				}
			});
		}
		
		/** @var \DBTech\Shop\ItemType\AbstractHandler|ConfigurableInterface $handler */
		$handler = $purchase->handler;
		if ($handler->isConfigurable())
		{
			$configuration = $this->filter('code', 'array');
			$form->complete(function () use ($handler, $configuration)
			{
				$configuration = $handler->filterUserConfig($configuration);
				
				$errors = null;
				if (!$handler->validateUserConfig($configuration, $errors))
				{
					return $this->error($errors);
				}
				
				$handler->configure($configuration);

				return true;
			});
		}

		if ($purchase->isInsert() && $input['active'])
		{
			// This has to be done at the end so it runs after the above configuration
			$form->complete(function () use ($handler)
			{
				$handler->activate();

				return true;
			});
		}
		elseif ($purchase->isUpdate() && $purchase->isChanged('active'))
		{
			// This has to be done at the end so it runs after the above configuration
			$form->complete(function () use ($handler, $input)
			{
				if ($input['active'])
				{
					$handler->activate();
				}
				else
				{
					$handler->deactivate();
				}

				return true;
			});
		}
		
		return $form;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$this->assertPostOnly();
		
		if ($params->purchase_id)
		{
			/** @var \DBTech\Shop\Entity\Purchase $purchase */
			$purchase = $this->assertPurchaseExists($params->purchase_id);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\Purchase $purchase */
			$purchase = $this->em()->create('DBTech\Shop:Purchase');
		}
		
		$this->purchaseSaveProcess($purchase)->run();
		
		return $this->redirect($this->buildLink('dbtech-shop/purchases') . $this->buildLinkHash($purchase->purchase_id));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 */
	public function actionDelete(ParameterBag $params)
	{
		$purchase = $this->assertPurchaseExists($params->purchase_id);
		if ($this->isPost())
		{
			$handler = $purchase->handler;
			$handler->logIp(false)
				->discard($null, 'manual_admin')
			;
			return $this->redirect($this->buildLink('dbtech-shop/purchases'));
		}
		else
		{
			$viewParams = [
				'content' => $purchase,
				'confirmUrl' => $this->buildLink('dbtech-shop/purchases/delete', $purchase),
				'contentUrl' => $this->buildLink('dbtech-shop/purchases/edit', $purchase),
				'contentTitle' => $purchase->purchase_id
			];
			return $this->view('XF:Delete\Delete', 'public:delete_confirm', $viewParams);
		}
	}

	/**
	 * @param array $extraParams
	 * @return array
	 */
	protected function getSearcherParams(array $extraParams = []): array
	{
		/** @var \DBTech\Shop\Searcher\Purchase $searcher */
		$searcher = $this->searcher('DBTech\Shop:Purchase');
		
		$viewParams = [
			'criteria' => $searcher->getFormCriteria(),
			'sortOrders' => $searcher->getOrderOptions()
		];
		return $viewParams + $searcher->getFormData() + $extraParams;
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Purchase|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertPurchaseExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Purchase', $id, $with, $phraseKey);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Purchase|\XF\Mvc\Entity\Repository
	 */
	protected function getPurchaseRepo()
	{
		return $this->repository('DBTech\Shop:Purchase');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Item|\XF\Mvc\Entity\Repository
	 */
	protected function getItemRepo()
	{
		return $this->repository('DBTech\Shop:Item');
	}
}