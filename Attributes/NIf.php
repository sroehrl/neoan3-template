<?php

namespace Neoan3\Apps\Attributes;

class NIf
{
    function __construct()
    {

    }

    /**
     * @throws \Exception
     */
    function __invoke(\DOMAttr &$attr, $contextData = [])
    {

        $toString = $attr->nodeValue;
        //
        $obfuscated = preg_replace("/'[^']+'/", "_", $toString);
        preg_match_all("/([\p{L}a-z0-9.]+)/", $obfuscated, $matches, PREG_SET_ORDER);
        $hit = false;
        foreach ($matches as $match){
            if(isset($contextData[$match[0]])) {
                $toString = str_replace($match[0], $this->typeCheck($contextData[$match[0]]), $toString);
                $hit = true;
            }
        }
        if(!$hit){
            return $toString;
        }
        try{
            $result = eval("return $toString;");
        } catch (\ParseError $e) {
            throw new \Exception("Template-error: cannot evaluate `{$attr->nodeValue}`");
        }
        if(!$result){
            $attr->parentNode->parentNode->removeChild($attr->parentNode);
        } else {
            $attr->parentNode->removeAttribute($attr->name);
        }


    }
    function typeCheck(mixed $value): mixed
    {
        switch (gettype($value)){
            case 'boolean': $value = $value ? 'true' : 'false'; break;
            case 'string': $value = "'$value'"; break;
        }

        return $value;
    }
}