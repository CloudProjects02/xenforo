<?php

namespace XFES;

use XF\Container;
use XFES\Install\Data\MySql;

class Listener
{
	public static function searchSourceSetup(Container $c, &$source)
	{
		$options = \XF::app()->options();
		if ($options->xfesEnabled)
		{
			$config = $options->xfesConfig;
			$es = self::getElasticsearchApi($config);

			$class = \XF::extendClass('XFES\Search\Source\Elasticsearch');
			$source = new $class($es);

			return false;
		}

		return true;
	}

	/**
	 * @param array $config
	 *
	 * @return Elasticsearch\Api
	 */
	public static function getElasticsearchApi(?array $config = null)
	{
		$liveConfig = \XF::app()->options()->xfesConfig;

		if ($config === null)
		{
			$config = $liveConfig;
		}
		else
		{
			$config = array_replace($liveConfig, $config);
		}

		$class = \XF::extendClass('XFES\Elasticsearch\Api');

		return new $class($config);
	}

	public static function getInstallData(array &$tableData)
	{
		$tableData['XFES'] = new MySql();
	}
}
