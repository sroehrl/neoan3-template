<?php

namespace Neoan3\Apps;

use DOMNodeList;
use Neoan3\Apps\Attributes\NFor;
use Neoan3\Apps\Attributes\NIf;

class Interpreter
{
    private array $contextData;
    private \DOMDocument $doc;
    private static array $customAttributes = [];
    private bool $isFragment = false;
    private string $encoding = 'utf-8';
    private bool $skipEncoding;
    private static array $customFunctions = [];
    private string $html;
    private static array $delimiter = ['{{','}}'];
    function __construct($html, $contextData, $skipEncoding = false)
    {

        // play:
        self::$customAttributes = ['n-for' => new NFor(), 'n-if' => new NIf()];

        $this->html = $html;
        $this->skipEncoding = $skipEncoding;
        $this->contextData = $this->flattenArray($contextData);

    }
    function addCustomFunction(string $name, callable $callable):void
    {
        self::$customFunctions[$name] = $callable;
    }
    function addCustomAttribute(string $name, callable $instance):void
    {
        self::$customAttributes[$name] = $instance;
    }
    function setEncoding(string $encoding):void
    {
        $this->encoding = $encoding;
    }
    function parse(): void
    {
        $this->html = $this->ensureEncoding($this->html);
        $this->doc = new \DOMDocument();
        @$this->doc->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->stepThrough($this->doc->childNodes);
    }
    private function getFragmentCompletions(): array
    {
        return [
            "<!DOCTYPE html><html><head><meta content=\"text/html; charset={$this->encoding}\" http-equiv=\"Content-Type\"></head><body>",
            "</body></html>"
        ];
    }
    private function ensureEncoding(string $html): string
    {
        if(!$this->skipEncoding && !str_contains($html, '<!DOCTYPE html>')){
            $partial = $this->getFragmentCompletions();
            $this->isFragment = true;
            $html = $partial[0] .$html . $partial[1];
        }
        return $html;
    }

    function asHtml(): string
    {
        if(!isset($this->doc)){
            $this->parse();
        }
        $output = $this->doc->saveHTML();
        if($this->isFragment){
            $partials = $this->getFragmentCompletions();
            $output = substr($output, strlen($partials[0]) +1,-1 * (strlen($partials[1])+1));
        }
        return $output;
    }
    function stepThrough(DOMNodeList $nodes): void
    {
        foreach($nodes as $child){
            if($child instanceof \DOMElement){
                if($child->hasAttributes()){
                    $this->handleAttributes($child);
                    // attribute functions?
                }
            }
            if($child instanceof \DOMText && trim($child->nodeValue) !== ''){
                // readDelimiter
                $child->nodeValue = $this->readDelimiter($child->nodeValue);
                // handle functions
                $this->handleFunctions($child);
            }


            $this->stepThrough($child->childNodes);
        }
    }
    function handleFunctions(\DOMText $element): void
    {
        foreach (self::$customFunctions as $function => $closure){
            $delimiter = self::$delimiter;
            $pattern = "/({$delimiter[0]}.*)*$function\(([^)]+)\)(.*{$delimiter[1]})*/";
            $hit = preg_match_all($pattern, $element->nodeValue, $matches, PREG_SET_ORDER);
            if($hit){
                foreach($matches as $match){
                    if(isset($this->contextData[$match[2]])){
                        $element->nodeValue = str_replace($match[0], $closure($this->contextData[$match[2]]), $element->nodeValue);
                    }
                }

            }
        }
    }
    function handleAttributes(\DOMElement $element): void
    {
        for($i = 0; $i < $element->attributes->count(); $i++){
            $attribute = $element->attributes->item($i);


            // 1. try embrace
            $attribute->nodeValue = htmlspecialchars($this->readDelimiter($attribute->nodeValue));
            // 2. try custom attributes
            $this->applyCustomAttributes($attribute);
        }
    }
    function applyCustomAttributes(\DOMAttr &$attribute): void
    {
        if(isset(self::$customAttributes[$attribute->name])){
            self::$customAttributes[$attribute->name]($attribute, $this->contextData);
        }

    }
    function readDelimiter(string $string): string
    {
        $delimiter = self::$delimiter;
        $pattern = "/{$delimiter[0]}([^{$delimiter[1]}]+){$delimiter[1]}/";

        $found = preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);
        if($found){
            foreach ($matches as $pair){
                if(isset($this->contextData[trim($pair[1])])){
                    $string = str_replace($pair[0], $this->contextData[trim($pair[1])], $string);
                }
            }

        }
        return $string;
    }
    /**
     * @param      $array
     * @param string|null $parentKey
     *
     * @return array
     */
    function flattenArray($array, string $parentKey = null): array
    {
        $answer = [];
        foreach ($array as $key => $value) {
            if ($parentKey) {
                $key = $parentKey . '.' . $key;
            }
            if (!is_array($value)) {
                $answer[$key] = $value;
            } else {
                $answer[$key] = 'Array';
                $answer[$key.'.length'] = sizeof($value);
                $answer = array_merge($answer, self::flattenArray($value, $key));
            }
        }
        return $answer;
    }

}