<?php
namespace AddonsLab\Core\Service;

class OptionBuilder
{
    /**
     * @param $flatArray array The array in key=>label format to convert
     * @param $selectedValue string|array if array, allow multiple selected items
     * @return array
     */
    public function convertToOptions($flatArray, $selectedValue)
    {
        $optionsArray = array();

        foreach ($flatArray AS $optionValue => $optionLabel) {
            $selected = (!is_array($selectedValue) && $optionValue==$selectedValue OR is_array($selectedValue) && in_array($optionValue, $selectedValue));

            $optionsArray[$optionValue] = array(
                'value' => $optionValue,
                'label' => $optionLabel,
                'selected' => $selected,
                'depth' => 0,
            );
        }

        return $optionsArray;
    }
    
    public function getCommaSeparatedIdList($optionValue)
    {
        $optionValue=explode(',', $optionValue);
        $optionValue=array_map('intval', $optionValue);
        $optionValue=array_diff($optionValue, array(0));
        
        return $optionValue;
    }

    /**
     * @param string $option
     * @param string $subSeparator
     * @return array
     * Parses a multiline option into an array. If sub-separator is specified, a multi-dimensional array is returned by splitting each line by sub-separator
     */
    public function getMultilineAsArray($option, $subSeparator='')
    {
        $parsedOption=array();
        
        foreach (explode("\n", $option) AS $optionLine) {
            $optionLine=trim($optionLine);
            if(!$optionLine) {
                continue;
            }
            
            if($subSeparator) {
                $optionLine=explode($subSeparator, $optionLine);
                $optionLine=array_map('trim', $optionLine);
            }
            
            $parsedOption[]=$optionLine;
        }
        
        return $parsedOption;
    }
}