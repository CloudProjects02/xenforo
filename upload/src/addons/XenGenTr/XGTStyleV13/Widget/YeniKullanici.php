<?php

namespace XenGenTr\XGTStyleV13\Widget;

class YeniKullanici extends \XF\Widget\AbstractWidget
{
	protected $defaultOptions = ['rich_usernames' => true, 'show_counter' => false];

	public function render()
	{
		if (!\XF::visitor()->canViewMemberList())
		{
			return '';
		}

		$userFinder = $this->finder('XF:User')
			->isValidUser()
			->order('register_date', 'DESC')
			->limit(5);

		$viewParams = ['users' => $userFinder->fetch()];

		return $this->renderer('xgtSv13_yeni_kullanicilar', $viewParams);
	}

	public function verifyOptions(\XF\Http\Request $request, array &$options, &$error = null)
	{
		return true;
	}
}
