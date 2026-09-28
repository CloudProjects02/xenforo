<?php

namespace DBTech\Shop\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

/**
 * Class Lottery
 * @package DBTech\Shop\Repository
 */
class Lottery extends Repository
{
	/**
	 * @return Finder
	 */
	public function findLotteriesForList(): Finder
	{
		return $this->finder('DBTech\Shop:Lottery')->order('next_draw_date', 'DESC');
	}
	
	/**
	 * @return Finder
	 */
	public function findLotteryPrizesForList(): Finder
	{
		return $this->finder('DBTech\Shop:LotteryPrize')->order('title', 'DESC');
	}
	
	/**
	 * @return Finder
	 */
	public function findLotteriesToDraw(): Finder
	{
		return $this->finder('DBTech\Shop:Lottery')
			->where('next_draw_date', '<=', \XF::$time);
	}

	/**
	 * @param \DBTech\Shop\Entity\Lottery $lottery
	 *
	 * @return \XF\Mvc\Entity\Finder
	 */
	public function findLotteryTicketsForLottery(\DBTech\Shop\Entity\Lottery $lottery): Finder
	{
		return $this->finder('DBTech\Shop:LotteryTicket')
			->with('User')
			->where('lottery_id', $lottery->lottery_id)
			->where('draw_date', $lottery->next_draw_date)
			->where('lottery_prize_id', 0)
		;
	}
	
	/**
	 * @param bool $onlyActive
	 *
	 * @return array|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getLotteryPrizeTitlePairs(bool $onlyActive = false)
	{
		$lotteryPrizeFinder = $this->findLotteryPrizesForList();
		
		$lotteryPrizes = $lotteryPrizeFinder->fetch();
		if ($onlyActive)
		{
			$lotteryPrizes = $lotteryPrizes->filterViewable();
		}
		
		return $lotteryPrizes->pluckNamed('title', 'lottery_prize_id');
	}
	
	/**
	 * @param int $lotteryId
	 * @param array $lotteryPrizes
	 *
	 * @throws \InvalidArgumentException
	 */
	public function updateContentAssociations(int $lotteryId, array $lotteryPrizes)
	{
		$db = $this->db();
		$db->beginTransaction();
		
		$db->delete('xf_dbtech_shop_lottery_prize_map', 'lottery_id = ?', $lotteryId);
		
		$map = [];
		
		foreach ($lotteryPrizes AS $info)
		{
			if (empty($info['lottery_prize_id'])
				|| empty($info['currency_id'])
				|| empty($info['prize_amount'])
			) {
				continue;
			}
			
			$map[] = [
				'lottery_id' => $lotteryId,
				'lottery_prize_id' => $info['lottery_prize_id'],
				'currency_id' => $info['currency_id'],
				'prize_amount' => $info['prize_amount']
			];
		}
		
		if ($map)
		{
			$db->insertBulk('xf_dbtech_shop_lottery_prize_map', $map, false, false, 'IGNORE');
		}
		
		$this->rebuildContentAssociationCache([$lotteryId]);
		
		$db->commit();
	}
	
	/**
	 * @param array $lotteryIds
	 */
	public function rebuildContentAssociationCache(array $lotteryIds)
	{
		if (!$lotteryIds)
		{
			return;
		}
		
		$newCache = [];
		
		$lotteryAssociations = $this->finder('DBTech\Shop:LotteryPrizeMap')
			->where('lottery_id', $lotteryIds);
		
		/** @var \DBTech\Shop\Entity\LotteryPrizeMap $lotteryValue */
		foreach ($lotteryAssociations->fetch() AS $lotteryValue)
		{
			$lotteryMap = $lotteryValue->toArray();
			unset($lotteryMap['lottery_id']);
			
			$newCache[$lotteryValue->lottery_id][] = $lotteryMap;
		}
		
		foreach ($lotteryIds AS $lotteryId)
		{
			if (!isset($newCache[$lotteryId]))
			{
				$newCache[$lotteryId] = [];
			}
		}
		
		$this->updateAssociationCache($newCache);
	}
	
	/**
	 * @param array $cache
	 */
	protected function updateAssociationCache(array $cache)
	{
		$lotteryIds = array_keys($cache);
		$lotterys = $this->em->findByIds('DBTech\Shop:Lottery', $lotteryIds);
		
		foreach ($lotterys AS $lottery)
		{
			/** @var \DBTech\Shop\Entity\Lottery $lottery */
			$lottery->prizes = $cache[$lottery->lottery_id];
			$lottery->saveIfChanged();
		}
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	public function drawLotteries()
	{
		/** @var \DBTech\Shop\Entity\Lottery[]|ArrayCollection $lotteries */
		$lotteries = $this->findLotteriesToDraw()
			->fetch()
		;
		foreach ($lotteries as $lottery)
		{
			$this->draw($lottery);
		}
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Lottery $lottery
	 *
	 * @throws \XF\PrintableException
	 */
	protected function draw(\DBTech\Shop\Entity\Lottery $lottery)
	{
		$drawnNumbers = [
			'main'  => [],
			'bonus' => [],
		];
		foreach (['main', 'bonus'] as $key)
		{
			$usedNumbers = [];
			for ($i = 1; $i <= $lottery->numbers[$key]; $i++)
			{
				do
				{
					$num = mt_rand(1, $lottery->numbers['total']);
				}
				while (isset($usedNumbers[$num]));
				
				// Used number
				$usedNumbers[$num] = true;
				$drawnNumbers[$key][$i] = $num;
			}
			
			sort($drawnNumbers[$key], SORT_NUMERIC);
		}
		
		/** @var \DBTech\Shop\Entity\LotteryTicket[]|ArrayCollection $tickets */
		$tickets = $this->findLotteryTicketsForLottery($lottery)
			->fetch()
		;
		if (!$tickets)
		{
			$this->finalizeLotteryDraw($lottery, $drawnNumbers);
			return;
		}
		
		/** @var \DBTech\Shop\Repository\Currency $currencyRepo */
		$currencyRepo = $this->repository('DBTech\Shop:Currency');
		
		/** @var \DBTech\Shop\Entity\LotteryPrizeMap[]|ArrayCollection $possiblePrizes */
		$possiblePrizes = $lottery->getRelationFinder('PrizeMap')
			->order('prize_amount', 'DESC')
			->fetch()
		;
		foreach ($tickets as $ticket)
		{
			$matchedNumbers = [
				'main' 	=> 0,
				'bonus' => 0
			];
			
			foreach ($ticket->numbers as $num)
			{
				if (in_array($num, $drawnNumbers['main']))
				{
					// We matched a number, woo
					$matchedNumbers['main']++;
				}
				
				if (in_array($num, $drawnNumbers['bonus']))
				{
					// We matched a number, woo
					$matchedNumbers['bonus']++;
				}
			}
			
			foreach ($possiblePrizes as $possiblePrize)
			{
				if (
					$matchedNumbers['main'] < $possiblePrize->Prize->numbers['main']
					|| $matchedNumbers['bonus'] < $possiblePrize->Prize->numbers['bonus']
				) {
					// Didn't meet the requirement for this prize
					continue;
				}
				
				$ticket->fastUpdate('lottery_prize_id', $possiblePrize->lottery_prize_id);
				
				$currencyRepo->addCurrencyAmount(
					$possiblePrize->Currency,
					'lotteryprize',
					$possiblePrize->prize_amount,
					$ticket->User,
					'dbtech_shop_lottery',
					$lottery->lottery_id
				);
				
				break;
			}
		}
		
		$this->finalizeLotteryDraw($lottery, $drawnNumbers);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Lottery $lottery
	 * @param array $numbers
	 *
	 * @throws \XF\PrintableException
	 */
	protected function finalizeLotteryDraw(\DBTech\Shop\Entity\Lottery $lottery, array $numbers)
	{
		$history = $this->em->create('DBTech\Shop:LotteryHistory');
		$history->lottery_id = $lottery->lottery_id;
		$history->drawn_numbers = $numbers;
		$history->draw_date = $lottery->next_draw_date;
		$history->tickets_sold = $lottery->tickets_sold;
		$history->save();
		
		$drawnNumbers = $lottery->drawn_numbers;
		$drawnNumbers[$lottery->next_draw_date] = $numbers;
		
		$lottery->drawn_numbers = $drawnNumbers;
		$lottery->previous_draw_date = $lottery->next_draw_date;
		$lottery->tickets_sold = 0;
		
		if ($lottery->draw_interval_days)
		{
			$lottery->next_draw_date += (86400 * $lottery->draw_interval_days);
		}
		
		$lottery->save();
	}
}