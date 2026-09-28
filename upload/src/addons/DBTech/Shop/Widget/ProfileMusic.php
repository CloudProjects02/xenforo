<?php

namespace DBTech\Shop\Widget;

use XF\Widget\AbstractWidget;

/**
 * Class ProfileMusic
 *
 * @package DBTech\Shop\Widget
 */
class ProfileMusic extends AbstractWidget
{
	/** @var array */
	protected $defaultOptions = [
		'autoplay' => false,
	];
	
	/**
	 * @return \XF\Widget\WidgetRenderer
	 */
	public function render(): \XF\Widget\WidgetRenderer
	{
		$mp3url = '';

		if (!empty($this->contextParams['user']) && ($this->contextParams['user'] instanceof \XF\Entity\User))
		{
			/** @var \DBTech\Shop\XF\Entity\User $user */
			$user = $this->contextParams['user'];
			
			/** @var \DBTech\Shop\Entity\Purchase[]|\XF\Mvc\Entity\ArrayCollection $purchases */
			$purchases = $this->repository('DBTech\Shop:Purchase')
				->filterActivePurchasesForUser($user)
				->filter(function (\DBTech\Shop\Entity\Purchase $purchase): ?\DBTech\Shop\Entity\Purchase
				{
					if ($purchase->Item->item_type_id != 'profilemusic')
					{
						return null;
					}
					
					return $purchase;
				})
			;

			foreach ($purchases as $purchase)
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