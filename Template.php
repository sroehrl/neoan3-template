<?php


namespace Neoan3\Apps;


class Template
{
    /**
     * @param $content
     * @param $array
     *
     * @param string $opening
     * @param string $closing
     * @return mixed
     */
    static function embrace($content, $array, $opening = '{{', $closing = '}}')
    {
        $flatArray = self::flattenArray($array);
        $templateFunctions = ['nFor', 'nIf'];
        foreach ($templateFunctions as $function) {
            $content = self::enforceEmbraceInAttributes(TemplateFunctions::$function($content, $array));
        }

        foreach ($flatArray as $flatKey => $value){
            if(is_callable($value)){
                $content = TemplateFunctions::executeClosure($content,$flatKey,$value,$flatArray, false);
            } else {
                $content = str_replace($opening . $flatKey . $closing, $value, $content);
            }
        }
        return $content;
    }

    /**
     * @param $content
     * @param $array
     *
     * @return mixed
     */
    static function hardEmbrace($content, $array)
    {
        return self::embrace($content, $array, '[[', ']]');
    }

    /**
     * @param $content
     * @param $array
     *
     * @return mixed
     */
    static function tEmbrace($content, $array)
    {
        return str_replace(array_map('self::tBraces', array_keys($array)), array_values($array), $content);
    }

    /**
     * @param $location
     * @param $array
     *
     * @return mixed
     */
    static function embraceFromFile($location, $array)
    {
        $appRoot = defined('path') ? path : '';
        $file = file_get_contents($appRoot . '/' . $location);
        return self::embrace($file, $array);
    }


    /**
     * @param $input
     *
     * @return string
     */
    private static function tBraces($input)
    {
        return '<t>' . $input . '</t>';
    }

    /**
     * @param $parentDoc
     * @param $hitNode
     * @param $stringContent
     */
    static function clone(\DOMDocument $parentDoc, \DOMElement $hitNode, string $stringContent)
    {
        $newDD = new \DOMDocument();
        @$newDD->loadHTML(
            '<root>' . $stringContent . '</root>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS
        );
        foreach ($newDD->firstChild->childNodes as $subNode) {
            if ($subNode->hasChildNodes() > 0 && $subNode->childNodes->length > 0) {
                $isNode = $parentDoc->importNode($subNode, true);
                $hitNode->parentNode->appendChild($isNode);
            }
        }
        $hitNode->parentNode->removeChild($hitNode);
    }

    /**
     * @param $content
     *
     * @return string|string[]|null
     */
    private static function enforceEmbraceInAttributes($content)
    {
        return preg_replace('/="(.*)(%7B%7B)(.*)(%7D%7D)(.*)"/', '="$1{{$3}}$5"', $content);
    }

    /**
     * @param \DOMElement $domNode
     *
     * @return string
     */
    static function nodeStringify(\DOMElement $domNode)
    {
        $string = '<' . $domNode->tagName;
        foreach ($domNode->attributes as $attribute) {
            $string .= ' ' . $attribute->name . '="' . $attribute->value . '"';
        }
        $string .= '>';
        if ($domNode->hasChildNodes()) {
            foreach ($domNode->childNodes as $node) {
                $string .= $domNode->ownerDocument->saveHTML($node);
            }
        }
        $string .= '</' . $domNode->tagName . '>';
        return $string;
    }

    /**
     * @param      $array
     * @param bool $parentKey
     *
     * @return array
     */
    static function flattenArray($array, $parentKey = false)
    {
        $answer = [];
        foreach ($array as $key => $value) {
            if ($parentKey) {
                $key = $parentKey . '.' . $key;
            }
            if (!is_array($value)) {
                $answer[$key] = $value;
            } else {
                $answer = array_merge($answer, self::flattenArray($value, $key));
            }
        }
        return $answer;
    }
}