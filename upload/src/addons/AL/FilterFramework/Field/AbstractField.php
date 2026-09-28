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


namespace AL\FilterFramework\Field;

use AL\FilterFramework\Service\InputTransformer;

/**
 * Class AbstractField.
 *
 * @see InputTransformer for conversion
 */
abstract class AbstractField
{
    /**
     * @var string
     *             If put to AND, the query generated will force ALL matching conditions to be true
     *             instead of ANY
     */
    protected $match_type = 'OR';

    abstract public function getAsString();

    /**
     * @return int
     *             The order in which the field should be processed when filtering items by multiple fields. Lower order means earlier processing
     *             Fields with faster matching algorithms should be processed first
     */
    abstract public function getFilterOrder();

    /**
     * @return string
     *                The representation of field value as a string
     *                to be used in cache generation
     */
    public function getCacheId()
    {
        return $this->getAsString();
    }

    /**
     * @param string $match_type
     */
    public function setMatchType($match_type)
    {
        if (!in_array($match_type, ['AND', 'OR']))
        {
            throw new \LogicException('The match type should be one of values AND|OR.');
        }
        $this->match_type = $match_type;
    }

    /**
     * @return string
     */
    public function getMatchType()
    {
        return $this->match_type;
    }
}
