<?php

namespace DBTech\Shop\Repository;

use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryHistory;
use DBTech\Shop\Entity\LotteryPrizeMap;
use DBTech\Shop\Finder\LotteryFinder;
use DBTech\Shop\Finder\LotteryPrizeFinder;
use DBTech\Shop\Finder\LotteryPrizeMapFinder;
use DBTech\Shop\Finder\LotteryTicketFinder;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;
use XF\PrintableException;

class LotteryRepository extends Repository
{
	/**
	 * @return LotteryFinder
	 */
	public function findLotteriesForList(): LotteryFinder
	{
		return \XF::app()->finder(LotteryFinder::class)
			->order('next_draw_date', 'DESC')
		;
	}

	/**
	 * @return LotteryPrizeFinder
	 */
	public function findLotteryPrizesForList(): LotteryPrizeFinder
	{
		return \XF::app()->finder(LotteryPrizeFinder::class)
			->order('title', 'DESC')
		;
	}

	/**
	 * @return LotteryFinder
	 */
	public function findLotteriesToDraw(): LotteryFinder
	{
		return \XF::app()->finder(LotteryFinder::class)
			->where('next_draw_date', '<=', \XF::$time);
	}

	/**
	 * @param Lottery $lottery
	 *
	 * @return LotteryTicketFinder
	 */
	public function findLotteryTicketsForLottery(Lottery $lottery): LotteryTicketFinder
	{
		return \XF::app()->finder(LotteryTicketFinder::class)
			->with('User')
			->where('lottery_id', $lottery->lottery_id)
			->where('draw_date', $lottery->next_draw_date)
			->where('lottery_prize_id', 0)
		;
	}

	/**
	 * @param bool $onlyActive
	 *
	 * @return array|ArrayCollection
	 */
	public function getLotteryPrizeTitlePairs(bool $onlyActive = false): ArrayCollection|array
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
	public function updateContentAssociations(int $lotteryId, array $lotteryPrizes): void
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
			)
			{
				continue;
			}

			$map[] = [
				'lottery_id' => $lotteryId,
				'lottery_prize_id' => $info['lottery_prize_id'],
				'currency_id' => $info['currency_id'],
				'prize_amount' => $info['prize_amount'],
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
	public function rebuildContentAssociationCache(array $lotteryIds): void
	{
		if (!$lotteryIds)
		{
			return;
		}

		$newCache = [];

		$lotteryAssociations = \XF::app()->finder(LotteryPrizeMapFinder::class)
			->where('lottery_id', $lotteryIds);

		/** @var LotteryPrizeMap $lotteryValue */
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
	protected function updateAssociationCache(array $cache): void
	{
		$lotteryIds = array_keys($cache);
		$lotterys = \XF::app()->em()->findByIds(Lottery::class, $lotteryIds);

		foreach ($lotterys AS $lottery)
		{
			/** @var Lottery $lottery */
			$lottery->prizes = $cache[$lottery->lottery_id];
			$lottery->saveIfChanged();
		}
	}

	/**
	 * @throws PrintableException
	 */
	public function drawLotteries(): void
	{
		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Lottery> $lotteries */
		$lotteries = $this->findLotteriesToDraw()
			->fetch()
		;
		foreach ($lotteries AS $lottery)
		{
			$this->draw($lottery);
		}
	}

	/**
	 * @param Lottery $lottery
	 *
	 * @throws PrintableException
	 */
	protected function draw(Lottery $lottery): void
	{
		$drawnNumbers = [
			'main'  => [],
			'bonus' => [],
		];
		foreach (['main', 'bonus'] AS $key)
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

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\LotteryTicket> $tickets */
		$tickets = $this->findLotteryTicketsForLottery($lottery)
			->fetch()
		;
		if (!$tickets)
		{
			$this->finalizeLotteryDraw($lottery, $drawnNumbers);
			return;
		}

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\LotteryPrizeMap> $possiblePrizes */
		$possiblePrizes = $lottery->getRelationFinder('PrizeMap')
			->order('prize_amount', 'DESC')
			->fetch()
		;
		foreach ($tickets AS $ticket)
		{
			$matchedNumbers = [
				'main' 	=> 0,
				'bonus' => 0,
			];

			foreach ($ticket->numbers AS $num)
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

			foreach ($possiblePrizes AS $possiblePrize)
			{
				if (
					$matchedNumbers['main'] < $possiblePrize->Prize->numbers['main']
					|| $matchedNumbers['bonus'] < $possiblePrize->Prize->numbers['bonus']
				)
				{
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
	 * @param Lottery $lottery
	 * @param array $numbers
	 *
	 * @throws PrintableException
	 */
	protected function finalizeLotteryDraw(Lottery $lottery, array $numbers): void
	{
		$history = \XF::app()->em()->create(LotteryHistory::class);
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