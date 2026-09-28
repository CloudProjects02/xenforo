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
use AL\FilterFramework\Field\AbstractField;
use AL\FilterFramework\Field\ColorField;
use AL\FilterFramework\Field\FloatField;
use AL\FilterFramework\Field\FreeTextField;
use AL\FilterFramework\Field\IntField;
use AL\FilterFramework\Field\LocationField;
use AL\FilterFramework\Field\MultipleChoiceField;
use AL\FilterFramework\Field\SingleChoiceField;
use AL\FilterFramework\FilterApp;
use AL\LocationField\Constants;
use XF\App;
use XF\Mvc\Entity\Finder;
use XF\Service\AbstractService;

class FieldIndexAccessor extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @param AbstractField[] $fieldList
     *                                   Sets up any finder object to join field index table and search
     */
    public function setupParentFinder(Finder $parentFinder, array $fieldList)
    {
        foreach ($fieldList as $fieldId => $field)
        {
            $indexRelationName = "CustomFieldIndex|{$fieldId}";
            $indexRelationPrefix = $indexRelationName . '.';
            if ($field instanceof ColorField)
            {
                $labColor = $field->getLabColor();
                $indexRelationName ? $parentFinder->with($indexRelationName, true) : false;
                $color_l = $parentFinder->columnSqlName("{$indexRelationPrefix}field_color_l");
                $color_a = $parentFinder->columnSqlName("{$indexRelationPrefix}field_color_a");
                $color_b = $parentFinder->columnSqlName("{$indexRelationPrefix}field_color_b");
                $distance = $this->contentTypeProvider->getColorSimilarityIndex();
                $condition = " SQRT ( POWER({$color_l}-{$labColor['l']}, 2) + POWER({$color_a}-{$labColor['a']}, 2) + POWER({$color_b}-{$labColor['b']}, 2) ) <= {$distance} ";
                $parentFinder->whereSql($condition);

                continue;
            }

            if ($field instanceof LocationField)
            {
                if ($field->getSearchFormat() === Constants::SEARCH_FORMAT_RANGE)
                {
                    /** @var GeoLocator $locator */
                    $locator = FilterApp::getGeoLocator();
                    $coordinates = $locator->getAddressCoordinates($field->getGetAddress(), $field->getCountryCode(), $field->getPlaceId());
                    if ($coordinates === null)
                    {
                        $parentFinder->whereImpossible();
                    }
                    else
                    {
                        $range = $locator->getCoordinateIntervals($coordinates, $field->getRange(), $field->getUnit());
                        $lat = $parentFinder->columnSqlName("{$indexRelationPrefix}field_location_lat");
                        $lng = $parentFinder->columnSqlName("{$indexRelationPrefix}field_location_lng");
                        $condition = "
                        {$lat}>{$range['latMin']}
                        AND {$lat}<{$range['latMax']}
                        AND {$lng}>{$range['lngMin']}
                        AND {$lng}<{$range['lngMax']}
                    ";
                        $parentFinder->whereSql($condition);
                    }
                }

                if ($field->getSearchFormat() === Constants::SEARCH_FORMAT_ADDRESS)
                {
                    if ($field->getCountryCode())
                    {
                        $parentFinder->where("{$indexRelationPrefix}field_location_country_code", $field->getCountryCode());
                        if ($field->getStateId())
                        {
                            $parentFinder->where("{$indexRelationPrefix}field_location_state_id", $field->getStateId());
                            if ($field->getCityId())
                            {
                                $parentFinder->where("{$indexRelationPrefix}field_location_city_id", $field->getCityId());
                            }
                        }
                    }
                }

                continue;
            }

            if ($field instanceof IntField)
            {
                $indexRelationName ? $parentFinder->with($indexRelationName, true) : false;
                $parentFinder->where("{$indexRelationPrefix}field_int", $field->getOperator(), $field->getValue());

                continue;
            }

            if ($field instanceof FloatField)
            {
                $indexRelationName ? $parentFinder->with($indexRelationName, true) : false;
                $parentFinder->where("{$indexRelationPrefix}field_float", $field->getOperator(), $field->getValue());

                continue;
            }

            if (
                $field instanceof SingleChoiceField
                || $field instanceof MultipleChoiceField
            ) {
                $indexTable = "{$indexRelationPrefix}SearchIndex";
                $parentFinder->with($indexTable, true);
                $metadataColumn = $parentFinder->columnSqlName("{$indexTable}.metadata");
                $conditions = $field->getMetadataMatchConditions($metadataColumn);
                if ($conditions)
                {
                    $parentFinder->whereSql(implode(' ' . $field->getMatchType() . ' ', $conditions));
                }

                continue;
            }

            if ($field instanceof FreeTextField)
            {
                $keywords = \XF::app()->search()->getParsedKeywords($field->getAsString(), $error, $warning);
                $indexTable = "{$indexRelationPrefix}SearchIndex";
                $parentFinder->with($indexTable, true);
                // Newer versions of mariadb require index on all columns to be matched
                $messageColumn = implode(', ', [
                    $parentFinder->columnSqlName("{$indexTable}.message"),
                    $parentFinder->columnSqlName("{$indexTable}.title"),
                    $parentFinder->columnSqlName("{$indexTable}.metadata"),
                ]);
                if ($keywords)
                {
                    $parentFinder->whereSql("MATCH({$messageColumn}) AGAINST (%s IN BOOLEAN MODE)", $keywords);
                }

                continue;
            }

            throw new \RuntimeException('Unhandled field type - ' . get_class($field));
        }
    }
}
