<?php

namespace XFES\Cron;

class SimilarThreads
{
	public static function updateCaches()
	{
		\XF::app()->jobManager()->enqueueUnique(
			'xfesSimilarThreads',
			'XFES:SimilarThreads',
			[],
			false
		);
	}
}
