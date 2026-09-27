<?php

namespace DBTech\Shop\Widget;

use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Entity\User;
use XF\Widget\AbstractWidget;
use XF\Widget\WidgetRenderer;

class ProfileMusic extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'autoplay' => false,
	];

	/**
	 * @return WidgetRenderer
	 */
	public function render(): WidgetRenderer
	{
		$mp3url = '';

		if (!empty($this->contextParams['user']) && ($this->contextParams['user'] instanceof User))
		{
			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = $this->contextParams['user'];

			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
			$purchases = \XF::app()->repository(PurchaseRepository::class)
				->filterActivePurchasesForUser($user)
				->filter(function (Purchase $purchase): ?Purchase
				{
					if ($purchase->Item->item_type_id != 'profilemusic')
					{
						return null;
					}

					return $purchase;
				})
			;

			foreach ($purchases AS $purchase)
			{
				if ($mp3url = $purchase->getConfiguration('url'))
				{
					// You can only display one Profile Music item
					break;
				}
			}
		}

		$viewParams = [
			'title' => $this->getTitle() ?: \XF::phrase('dbtech_shop_music_player'),
			'mp3url' => $mp3url,
		];
		return $this->renderer('dbtech_shop_widget_profilemusic', $viewParams);
	}
}