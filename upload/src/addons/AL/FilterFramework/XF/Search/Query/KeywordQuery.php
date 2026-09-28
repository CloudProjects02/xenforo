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


namespace AL\FilterFramework\XF\Search\Query;

class KeywordQuery extends XFCP_KeywordQuery
{
    protected $is_elastic_search = false;

    protected $multiple_keywords = [];

    public function withMultipleKeywords($keywords, $titleOnly = false)
    {
        $keywordList = array_map('trim', explode(',', $keywords));

        foreach ($keywordList as $keyword)
        {
            $this->withKeywords($keyword, $titleOnly);

            // Store the list of parsed keywords but remove the + sign
            $this->multiple_keywords[] = preg_replace('/\s\+/', ' ', preg_replace('/^\+/', '', $this->parsedKeywords));
        }

        // The keyword property should be set to original keyword list anyway
        $this->keywords = $keywords;

        // Use the MySQL syntax by default
        $this->parsedKeywords = '+(' . implode(' OR ', $this->multiple_keywords) . ')';
    }

    public function setIsElasticSearch(bool $is_elastic_search): void
    {
        $this->is_elastic_search = $is_elastic_search;
    }

    public function getParsedKeywords()
    {
        if (!$this->is_elastic_search || empty($this->multiple_keywords))
        {
            return parent::getParsedKeywords();
        }

        // Use the elastic syntax by grouping e.g. (Tempus libero)|(Luctus)|(erat tellus)
        $keywords = array_map(static function ($keyword) {
            return '(' . implode(' ', explode(' ', $keyword)) . ')';
        }, $this->multiple_keywords);

        return implode('|', $keywords);
    }
}
