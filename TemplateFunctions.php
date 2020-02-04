<?php


namespace Neoan3\Apps;


class TemplateFunctions
{
    static function executeClosure($content, $callBackName, $closure, $valueArray, $pure = true){
        $pattern = "/" . ($pure ? "" : "\{\{") . "$callBackName\(([a-z0-9,\s]+)\)" . ($pure ? "" : "\}\}") . "/i";
        return preg_replace_callback($pattern, function($hit) use ($closure, $valueArray){
            return $closure($valueArray[$hit[1]]);
        },$content);
    }
    private static function extractAttribute(\DOMElement $hit, $attribute)
    {
        // extract attribute
        $parts = explode(' ', $hit->getAttribute($attribute));
        // clean up
        foreach ($parts as $i => $part) {
            if (empty(trim($part))) {
                unset($parts[$i]);
            }
        }
        $parts = array_values($parts);
        // remove attribute
        $hit->removeAttribute($attribute);
        // while string
        return ['template' => Template::nodeStringify($hit), 'parts' => $parts];
    }

    static private function subContentGeneration(
        \DOMDocument $domDocument,
        \DOMElement $hit,
        array $paramArray,
        array $parts,
        string $template
    ) {
        $newContent = '';
        if (isset($paramArray[$parts[0]]) && !empty($paramArray[$parts[0]])) {
            $subArray = [];
            foreach ($paramArray[$parts[0]] as $key => $value) {
                if (isset($parts[4])) {
                    $subArray[$parts[2]] = $key;
                    $subArray[$parts[4]] = $value;
                } else {
                    $subArray[$parts[2]] = $value;
                }
                $newContent .= Template::embrace($template, $subArray);
            }
            Template::clone($domDocument, $hit, $newContent);
        }
        return $newContent;
    }

    /**
     * @param $content
     * @param $array
     *
     * @return string|string[]|null
     */
    static function nFor($content, $array)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xPath = new \DOMXPath($doc);
        $hits = $xPath->query("//*[@n-for]");
        if ($hits->length < 1) {
            return $content;
        }
        foreach ($hits as $hit) {
            $extracted = self::extractAttribute($hit, 'n-for');
            self::subContentGeneration($doc, $hit, $array, $extracted['parts'], $extracted['template']);
        }
        return $doc->saveHTML();
    }
    private static function evaluateTypedCondition(array $flatArray, $expression){
        $bool = true;
        foreach ($flatArray as $key => $value) {
            if (strpos($expression, $key) !== false) {
                switch (gettype($flatArray[$key])) {
                    case 'boolean':
                        $expression = str_replace($key, $flatArray[$key] ? 'true' : 'false', $expression);
                        break;
                    case 'NULL':
                        $expression = str_replace($key, 'false', $expression);
                        break;
                    case 'string':
                        $expression = str_replace($key, '"' . $flatArray[$key] . '"', $expression);
                        break;
                    case 'object':
                        $expression = self::executeClosure($expression,$key,$flatArray[$key],$flatArray);
                        break;
                    default:
                        $expression = str_replace($key, $flatArray[$key], $expression);
                        break;
                }
                $bool = eval("return $expression;");
            }
        }
        return $bool;
    }

    /**
     * @param $content
     * @param $array
     *
     * @return string
     */
    static function nIf($content, $array)
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xPath = new \DOMXPath($doc);
        $hits = $xPath->query("//*[@n-if]");
        if ($hits->length < 1) {
            return $content;
        }

        foreach ($hits as $hit) {
            $expression = $hit->getAttribute('n-if');
            $array = Template::flattenArray($array);
            $bool = self::evaluateTypedCondition($array, $expression);

            if (!$bool) {
                $hit->parentNode->removeChild($hit);
            } else {
                $hit->removeAttribute('n-if');
            }
        }
        return $doc->saveHTML();
    }
}