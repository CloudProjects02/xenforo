<?php

namespace SV\SignupAbuseBlocking\Repository;

use GeoIp2\Database\Reader;
use GuzzleHttp\Exception\RequestException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use SV\StandardLib\Helper;
use XF\Mvc\Entity\Repository;
use XF\Util\File;
use function array_key_exists;
use function file_exists;
use function file_put_contents;
use function is_readable;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;

class MaxMind extends Repository
{
    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    /** @var array<string,Reader|null> */
    protected $readers = [];

    protected function getMaxMindReader(string $database): ?Reader
    {
        if (array_key_exists($database, $this->readers))
        {
            return $this->readers[$database];
        }

        $file = File::getCodeCachePath() . '/svSignupAbuseBlocking/' . $database . '.mmdb';
        if (!file_exists($file) || !is_readable($file))
        {
            $this->readers[$database] = null;

            return null;
        }

        try
        {
            $this->readers[$database] = new Reader($file);
        }
        catch (InvalidDatabaseException $e)
        {
            \XF::logException($e, false, "[SignupAbuseBlocking] Invalid GeoLite2 database ($database): ");

            $this->readers[$database] = null;

            return null;
        }

        return $this->readers[$database];
    }

    protected function updateMaxMindDatabase(string $database, string $licenseKey): bool
    {
        if (!(\XF::options()->svSignupAbuseBlockingMaxMindUpdate ?? true))
        {
            return false;
        }

        try
        {
            $tempFile = File::getNamedTempFile($database . '.tar.gz');
            $tarFile = File::getNamedTempFile($database . '.tar');
            $extractionDir = File::createTempDir();
            $cacheFile = 'code-cache://svSignupAbuseBlocking/' . $database . '.mmdb';

            $options = $this->options();
            if ($licenseKey === '')
            {
                $licenseKey = $options->svSignupAbuseBlockingMaxMindApiKey ?? '';
            }
            if ($licenseKey === '')
            {
                $licenseKey = (string)($options->dbtechEcommerceMaxMindApiKey ?? '');
            }

            if ($licenseKey === '')
            {
                //\XF::logError("[SignupAbuseBlocking] Error updating GeoLite2 database ($database): No MaxMind API Key has been set. Check your settings.");

                return false;
            }

            $response = $this->app()->http()->reader()->getUntrusted('https://download.maxmind.com/app/geoip_download?edition_id=' . $database . '&license_key=' . $licenseKey . '&suffix=tar.gz');
            $stream = $response->getBody();

            do
            {
                file_put_contents($tempFile, $stream->read(2048), FILE_APPEND);
            }
            while (!$stream->eof());

            @stream_wrapper_restore('phar');

            // decompress from gz
            $p = new \PharData($tempFile);
            $p->decompress(); // creates /path/to/my.tar

            $filePath = $p->getFilename() . '/' . $database . '.mmdb';

            // unarchive from the tar
            $phar = new \PharData($tarFile);
            $phar->extractTo($extractionDir, $filePath);

            File::copyFileToAbstractedPath($extractionDir . '/' . $filePath, $cacheFile);

            @stream_wrapper_unregister('phar');
        }
        catch (RequestException|\UnexpectedValueException|\BadMethodCallException $e)
        {
            \XF::logException($e, false, "[SignupAbuseBlocking] Error updating GeoLite2 database ($database): ");

            return false;
        }

        return true;
    }

    public function updateGeoIpDb(string $maxMindKey = ''): bool
    {
        return $this->updateMaxMindDatabase('GeoLite2-Country', $maxMindKey);
    }

    public function getGeoIpReader(): ?Reader
    {
        return $this->getMaxMindReader('GeoLite2-Country');
    }

    public function updateAsnDb(string $maxMindKey = ''): bool
    {
        return $this->updateMaxMindDatabase('GeoLite2-ASN', $maxMindKey);
    }

    public function getAsnReader(): ?Reader
    {
        return $this->getMaxMindReader('GeoLite2-ASN');
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
