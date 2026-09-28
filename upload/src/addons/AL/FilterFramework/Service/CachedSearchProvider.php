<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Service;

use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\Entity\SearchCache;
use AL\FilterFramework\Field\AbstractField;
use AL\FilterFramework\FilterApp;
use XF\App;
use XF\Db\DuplicateKeyException;
use XF\PrintableException;
use XF\Service\AbstractService;

class CachedSearchProvider extends AbstractService
{
    protected $contentTypeProvider;

    protected $disableCache = false;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @param AbstractField[] $searchList
     *
     * @return string Unique md5 hash representing the search, takes into account nodes being searched
     */
    public function getSearchHash(array $searchList, array $metadata)
    {
        $hashData = [];

        foreach ($searchList as $fieldId => $field)
        {
            $hashData[] = $fieldId . '=' . $field->getCacheId();
            $hashData[] = 'match=' . $field->getMatchType();
        }

        $hashData[] = json_encode($metadata);

        // changing color similarity index can change the results, so the cache should include it
        $hashData[] = $this->contentTypeProvider->getColorSimilarityIndex();

        $hashData[] = $this->contentTypeProvider->getFilterName();

        // Options that affect the search results
        $hashData[] = \XF::options()->alff_tag_match_mode;

        return md5(implode(',', $hashData));
    }

    /**
     * @param array $filterData data submitted by users via filter forms
     *
     * @return array Item IDs matching all fields selected
     *
     * @throws PrintableException
     */
    public function executeSearch($filterData, array $metadata)
    {
        $logger = FilterApp::getFilterLogger($this->contentTypeProvider);
        // $logger->info('Executing search with filter data: ' . json_encode($filterData, JSON_THROW_ON_ERROR) . ' and metadata ' . json_encode($metadata, JSON_THROW_ON_ERROR));

        // by default an empty array will be returned
        $defaultResult = [];

        if (!is_array($filterData))
        {
            $logger->error('Filter data is not an array');

            // filter data was not set in the search
            return null;
        }

        if (empty($metadata['category_ids']))
        {
            $logger->info('No category IDs were provided');
            unset($metadata['category_ids']);
        }

        if (empty($metadata['__metadata_primary_key_ids']))
        {
            $logger->info('No primary key IDs were provided');
            unset($metadata['__metadata_primary_key_ids']);
        }

        FilterApp::getExtendedAttributeHandler($this->contentTypeProvider)->setupSearchMetadata($filterData, $metadata);

        // $logger->debug('Filter data before normalization: ' . json_encode($filterData, JSON_THROW_ON_ERROR));
        FilterApp::getInputTransformer($this->contentTypeProvider)->normalizeInput($filterData);
        // $logger->debug('Filter data after normalization: ' . json_encode($filterData, JSON_THROW_ON_ERROR));

        $searchList = FilterApp::getInputTransformer($this->contentTypeProvider)->convertInputToFieldArray($this->contentTypeProvider, $filterData, $metadata);

        $searchListDebug = array_map(function (AbstractField $field) {
            return $field->getAsString();
        }, $searchList);
        // $logger->debug('Search list: ' . json_encode($searchListDebug, JSON_THROW_ON_ERROR));

        if (empty($searchList) && empty($metadata))
        {
            $logger->info('Metadata is empty and search list is empty, returning default result');

            // no filter
            return null;
        }

        uasort($searchList, function (AbstractField $field1, AbstractField $field2) {
            return $field1->getFilterOrder() - $field2->getFilterOrder();
        });

        $searchHash = $this->getSearchHash($searchList, $metadata);

        $cacheTtl = \XF::options()->offsetExists($this->contentTypeProvider->getOptionPrefix() . '_cache_ttl') ? \XF::options()->offsetGet($this->contentTypeProvider->getOptionPrefix() . '_cache_ttl') : 3600;

        if ($this->disableCache === false && $cacheTtl > 0)
        {
            $logger->info('Cache is enabled, checking cache');

            /** @var SearchCache $existingSearch */
            $existingSearch =
                \XF::finder('AL\FilterFramework:SearchCache')->whereId(['content_type' => $this->contentTypeProvider->getContentType(), 'search_hash' => $searchHash])->fetchOne();

            // cache is valid for 60 minutes

            if ($existingSearch && $existingSearch->creation_date > time() - $cacheTtl)
            {
                $logger->info('Cache is valid, returning result - ' . $existingSearch->result_list);
                $result_list = $existingSearch->result_list ? explode(',', $existingSearch->result_list) : [];

                return array_map('intval', $result_list);
            }
        }

        FilterApp::getExtendedAttributeHandler($this->contentTypeProvider)->preSearch($metadata);

        // ask the content handler to execute the actual search and return the results

        // handle errors during search execution
        try
        {
            $results = $this->contentTypeProvider->executeSearch($searchList, $metadata);
            $logger->debug('Search results: ' . json_encode($results, JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
        catch (\Exception $exception)
        {
            $logger->error('An error occurred during search execution: ' . $exception->getMessage());
            \XF::logException($exception);
            $results = $defaultResult;
        }

        if (empty($results))
        {
            $logger->info('Search returned no results, returning default result');
            $results = $defaultResult; // no items matched
        }

        if ($this->disableCache || !$cacheTtl)
        {
            // Find the cache and delete it if exists
            $cacheEntity = \XF::finder('AL\FilterFramework:SearchCache')->where('search_hash', $searchHash)->fetchOne();
            if ($cacheEntity)
            {
                $cacheEntity->delete();
            }
        }

        /** @var SearchCache $cache */
        $cache = \XF::em()->create('AL\FilterFramework:SearchCache');
        $cache->search_hash = $searchHash;
        $cache->content_type = $this->contentTypeProvider->getContentType();
        $cache->result_list = implode(',', $results);
        $cache->field_list = implode(',', array_keys($searchList));
        $cache->creation_date = time();

        try
        {
            $cache->save();
        }
        catch (DuplicateKeyException $exception)
        {
            $logger->debug('Duplicate key exception while saving search cache: ' . $exception->getMessage());

            try
            {
                $cacheEntity = \XF::finder('AL\FilterFramework:SearchCache')->where('search_hash', $searchHash)->fetchOne();

                if ($cacheEntity !== null)
                {
                    $cacheEntity->delete();
                }

                $cache->save();
            }
            catch (\Exception $exception)
            {
                $logger->debug('An error occurred while deleting duplicate search cache: ' . $exception->getMessage());
                // might be race condition, skip saving from this process
            }
        }
        catch (\Exception $exception)
        {
            $logger->debug('Race condition while saving search cache');
            // might be race condition, skip saving from this process
        }

        return $results;
    }

    /**
     * @return bool
     */
    public function isDisableCache()
    {
        return $this->disableCache;
    }

    /**
     * @param bool $disableCache
     */
    public function setDisableCache($disableCache)
    {
        $this->disableCache = $disableCache;
    }
}
