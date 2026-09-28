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

use AL\FilterFramework\ActiveFilterInfo;
use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\FilterApp;
use AL\FilterFramework\Flags as FlagsAlias;
use AL\FilterFramework\XF\Search\Query\KeywordQuery;
use XF\App;
use XF\Http\Request;
use XF\Service\AbstractService;

/**
 * Class ExtendedAttributeSearcher.
 */
class ExtendedAttributeSearcher extends AbstractService
{
    protected $contentTypeProvider;

    protected $disableCache = false;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * Additional setup of metadata before it will be passed down to main custom field searcher
     * Can be used to pre-search for messages containing keywords, tags etc.
     * The results of this search are cached and will not be executed on subsequent pages.
     */
    public function preSearch(array &$metadata)
    {
        $logger = FilterApp::getFilterLogger($this->contentTypeProvider);

        $logger->debug('Pre-searching for extended attributes');

        $itemIds = $metadata['__metadata_primary_key_ids'] ?? [];

        $hasTags = !empty($metadata['__tags']) && $this->canSearchTags();
        $hasKeywords = !empty($metadata['__keywords']) && $this->canSearchKeywords();

        $prefixMetadataSearch = !empty($metadata[FlagsAlias::MULTI_PREFIX_KEY]) ? $metadata[FlagsAlias::MULTI_PREFIX_KEY] : [];

        $prefixMetadataSearch = array_filter(array_map('intval', $prefixMetadataSearch));
        $prefixMetadataSearch = array_values($prefixMetadataSearch);

        $hasMultiPrefix = !empty($prefixMetadataSearch) && $this->canSearchMultiplePrefixes();

        $searcher = \XF::app()->search();

        /** @var KeywordQuery $query */
        $query = $searcher->getQuery();

        $constraints = [];

        if ($hasTags)
        {
            $logger->info('Executing tag search for ' . $metadata['__tags']);
            $tagRepo = \XF::repository('XF:Tag');
            $tags = $tagRepo->splitTagList($metadata['__tags']);
            $validTags = $tagRepo->getTags($tags, $notFound);
            $tagIds = array_keys($validTags);
            $forceSearch = \XF::options()->alff_tag_match_mode === 'all' && $notFound;
            if ($forceSearch)
            {
                // Invalid tags should trigger no results being shown
                $tagIds[] = PHP_INT_MAX;
            }
            if ($tagIds)
            {
                $logger->info('Found Tag IDs ' . implode(', ', $tagIds));
                $query->withTags($tagIds, \XF::options()->alff_tag_match_mode);
                $constraints['tag'] = implode(' ', $tagIds);
            }
        }

        if ($hasKeywords)
        {
            $logger->info('Executing keyword search for ' . $metadata['__keywords']);
            $keywords = \XF::app()->stringFormatter()->censorText($metadata['__keywords']);

            if ($this->contentTypeProvider->getMultipleKeywordSupport())
            {
                $query->withMultipleKeywords($keywords, $this->contentTypeProvider->getKeywordSearchSetting() === 'titles_only');
            }
            else
            {
                $query->withKeywords($keywords, $this->contentTypeProvider->getKeywordSearchSetting() === 'titles_only');
            }

            $constraints['keywords'] = $keywords;
        }

        if ($hasMultiPrefix)
        {
            $logger->info('Executing multi prefix search for ');
            $query->withMetadata($this->contentTypeProvider->getPrefixMetadataName(), $prefixMetadataSearch);
            $constraints[$this->contentTypeProvider->getPrefixMetadataName()] = $prefixMetadataSearch;
        }

        $logger->debug('Query before content-specific constraints: ' . json_encode($query, JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->contentTypeProvider->preSearch($query, $metadata, $constraints);
        $logger->debug('Query after content-specific constraints: ' . json_encode($query, JSON_PARTIAL_OUTPUT_ON_ERROR));

        if ($itemIds)
        {
            $logger->info('Executing item ID search for ' . (is_array($itemIds) ? implode(', ', $itemIds) : $itemIds));
            $query->withMetadata($this->contentTypeProvider->getContentType(), $itemIds);
        }

        if (empty($constraints))
        {
            $logger->info('No search constraints found, skipping search');

            // no pre-searching is needed
            return;
        }

        // add the constraint of content type as well
        // no need to set up anything by content handlers, so no data is given to the request except the content type
        $searchRequest = new Request($this->app->inputFilterer(), [
            'search_type' => $this->contentTypeProvider->getContentType(),
        ], [], []);

        $typeHandler = $searcher->handler(
            $this->contentTypeProvider->getContentType()
        );

        $query->forTypeHandler($typeHandler, $searchRequest, $constraints);

        $searchRepo = $this->repository('XF:Search');

        $cacheTtl = \XF::options()->offsetExists($this->contentTypeProvider->getOptionPrefix() . '_cache_ttl') ? \XF::options()->offsetGet($this->contentTypeProvider->getOptionPrefix() . '_cache_ttl') : 3600;

        // increase the size of results returned as this search runs before the main filter by custom fields
        \XF::options()->maximumSearchResults = $this->contentTypeProvider->getMaxResultCount() ?: \XF::options()->maximumSearchResults;

        $search = $searchRepo->runSearch($query, $constraints, $cacheTtl > 0);

        if ($search === null)
        {
            $logger->info('Extended attributes search failed, applying impossible condition.');
            // impossible condition as we don't have any results matching
            $itemIds = [-1];
        }
        else
        {
            $items = $search->search_results;

            $itemIds = array_map(static function ($item) {
                return $item[1];
            }, $items);

            if (empty($itemIds))
            {
                $logger->info('No search results found based on extended attributes. Apply impossible condition.');
                $itemIds = [-1];
            }
        }

        $metadata['__metadata_primary_key_ids'] = array_values($itemIds);
        $logger->info('Extended attributes search results: ' . implode(', ', $itemIds));
    }

    /**
     * Extracts the metadata search information from filter data provided and removes them from the array.
     *
     * @throws \JsonException
     */
    public function setupSearchMetadata(array &$filterData, array &$metadata)
    {
        $logger = FilterApp::getFilterLogger($this->contentTypeProvider);
        if (!empty($filterData['__keywords']) && $this->canSearchKeywords())
        {
            $metadata['__keywords'] = $filterData['__keywords'];
            $logger->info('Searching for keywords - ' . $metadata['__keywords']);
            unset($filterData['__keywords']);
        }
        if (!empty($filterData['__tags']) && $this->canSearchTags())
        {
            $metadata['__tags'] = $filterData['__tags'];
            $logger->info('Searching for tags - ' . $metadata['__tags']);
            unset($filterData['__tags']);
        }
        if (($prefixMetadata = $this->_getPreparedPrefixMetadata($filterData)) && $this->canSearchMultiplePrefixes())
        {
            $metadata[FlagsAlias::MULTI_PREFIX_KEY] = $prefixMetadata;
            // $logger->info('Searching for multiple prefixes - ' . json_encode($prefixMetadata, JSON_THROW_ON_ERROR));
            unset($filterData[FlagsAlias::MULTI_PREFIX_KEY]);
        }
    }

    /**
     * @param ActiveFilterInfo[] $filterData
     *
     * @return array
     */
    public function getActiveFilters(array $filterData)
    {
        $activeFilters = [];
        if (!empty($filterData['__keywords']) && $this->canSearchKeywords())
        {
            $filter = new ActiveFilterInfo();
            $filter->phrase_text = $filterData['__keywords'];
            $filter->field_type = 'keywords';
            $filter->showLabel = true;
            $filter->title = \XF::phrase('keywords');
            $activeFilters['__keywords'] = $filter;
        }

        if (!empty($filterData['__tags']) && $this->canSearchTags())
        {
            $filter = new ActiveFilterInfo();
            $filter->phrase_text = $filterData['__tags'];
            $filter->field_type = 'tags';
            $filter->showLabel = true;
            $filter->title = \XF::phrase('tags');
            $activeFilters['__tags'] = $filter;
        }

        if (($prefixMetadata = $this->_getPreparedPrefixMetadata($filterData)) && $this->canSearchMultiplePrefixes())
        {
            $prefixes = FilterApp::getPrefixProvider($this->contentTypeProvider)->getPrefixesByIds($prefixMetadata);

            foreach ($prefixes as $prefixId => $prefixTitle)
            {
                $filter = new ActiveFilterInfo();
                $filter->phrase_text = $prefixTitle;
                $filter->field_type = FlagsAlias::MULTI_PREFIX_KEY;
                $filter->showLabel = true;
                $filter->title = \XF::phrase('prefix');

                // setting field name customizes the field ID used in the templates
                $filter->field_name = FlagsAlias::MULTI_PREFIX_KEY;
                // setting sub_item_id makes sure to remove from the URL only this sub-item of the field
                // can be used in future for checkboxes as well
                $filter->sub_item_id = $prefixId;

                $activeFilters[FlagsAlias::MULTI_PREFIX_KEY . '_' . $prefixId] = $filter;
            }
        }

        return $activeFilters;
    }

    public function canSearchTags()
    {
        return $this->contentTypeProvider->getTagSearchSetting() !== 'disabled';
    }

    public function canSearchKeywords()
    {
        return $this->contentTypeProvider->getKeywordSearchSetting() !== 'disabled';
    }

    public function canSearchMultiplePrefixes()
    {
        return $this->contentTypeProvider->getMultiPrefixEnabledSetting() !== 'disabled' && $this->contentTypeProvider->getPrefixMetadataName();
    }

    protected function _getPreparedPrefixMetadata(array $filterData)
    {
        if (!empty($filterData[FlagsAlias::MULTI_PREFIX_KEY]))
        {
            $filterData[FlagsAlias::MULTI_PREFIX_KEY] = array_diff($filterData[FlagsAlias::MULTI_PREFIX_KEY], [0]);

            return array_merge($filterData[FlagsAlias::MULTI_PREFIX_KEY], []);
        }

        return [];
    }
}
