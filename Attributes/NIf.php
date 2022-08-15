<?php

namespace Neoan3\Apps\Template\Attributes;

use Error;
use Exception;
use Neoan3\Apps\Template\Constants;
use ParseError;

class NIf
{
    private bool $hit = false;
    function __construct()
    {

    }

    /**
     * @throws Exception
     */
    function __invoke(\DOMAttr &$attr, $contextData = []):void
    {
        $this->hit = false;
        $contextData = Constants::flattenArray($contextData);
        $toString = $attr->nodeValue;
        preg_match_all("/([\p{L}a-z0-9.]+)/", $toString, $matches, PREG_SET_ORDER);
        foreach ($matches as $match){
            if(array_key_exists($match[0], $contextData)) {
                $toString = str_replace($match[0], $this->typeCheck($contextData[$match[0]]), $toString);

                $this->hit = true;
            }

        }
        $this->evaluateCustomFunctions($toString);
        if(!$this->hit){
            return;
        }
        try{
            $result = eval("return $toString;");

        } catch (ParseError|Error $e) {
            return;
        }
        if(!$result){
            $attr->parentNode->parentNode->removeChild($attr->parentNode);
        } else {

            $attr->parentNode->removeAttribute($attr->name);
        }

    }
    function evaluateCustomFunctions(string &$currentString):void
    {
        foreach (Constants::getCustomFunctions() as $key => $execution){
            $pattern = "/($key\(([^\)]*)\))/";
            $currentString = preg_replace_callback($pattern, function($matches) use($key, $currentString){
                $this->hit = true;
                if(!empty($matches[2])){
                    return Constants::getCustomFunctions()[$key]($matches[2]);
                }
                return  Constants::getCustomFunctions()[$key]();
            }, $currentString);
        }

    }

    function typeCheck(mixed $value): mixed
    {
        switch (gettype($value)){
            case 'boolean': $value = $value ? 'true' : 'false'; break;
            case 'string': $value = "'$value'"; break;
            case 'NULL': $value = 'null'; break;
        }

        return $value;
    }
}