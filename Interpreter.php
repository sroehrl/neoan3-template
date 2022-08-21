<?php

namespace Neoan3\Apps\Template;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMText;

/**
 *
 */
class Interpreter
{
    /**
     * @var array
     */
    private array $contextData;
    /**
     * @var DOMDocument
     */
    private DOMDocument $doc;
    /**
     * @var bool
     */
    private bool $isFragment = false;
    /**
     * @var bool|mixed
     */
    private bool $skipEncoding;
    /**
     * @var string
     */
    private string $html;
    private array $flatData;


    /**
     * @param $html
     * @param $contextData
     * @param bool $skipEncoding
     */
    function __construct($html, $contextData, bool $skipEncoding = false)
    {

        $this->html = $html;
        $this->skipEncoding = $skipEncoding;
        $this->contextData = $contextData;
        $this->flatData = Constants::flattenArray($contextData);

    }

    /**
     * @return void
     */
    function parse(): void
    {
        $this->html = $this->ensureEncoding($this->html);
        $this->doc = new DOMDocument();
        @$this->doc->loadHTML($this->html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $this->stepThrough($this->doc->childNodes);
    }

    /**
     * @return string[]
     */
    private function getFragmentCompletions(): array
    {
        $encoding = Constants::getEncoding();
        return [
            "<!DOCTYPE html><html><head><meta content=\"text/html; charset=$encoding\" http-equiv=\"Content-Type\"></head><body>",
            "</body></html>"
        ];
    }

    /**
     * @param string $html
     * @return string
     */
    private function ensureEncoding(string $html): string
    {
        if(!$this->skipEncoding && !str_contains($html, '<!DOCTYPE html>')){
            $partial = $this->getFragmentCompletions();
            $this->isFragment = true;
            $html = $partial[0] .$html . $partial[1];
        }
        return $html;
    }

    /**
     * @return string
     */
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

    /**
     * @param DOMNodeList $nodes
     * @return void
     */
    function stepThrough(DOMNodeList $nodes): void
    {
        foreach($nodes as $child){
            if($child instanceof DOMElement){
                // attributes?
                if($child->hasAttributes()){
                    $this->handleAttributes($child);
                }
                // IS delimiter?
                $this->handleDelimiterIsTag($child);

            }
            if($child instanceof DOMText && trim($child->nodeValue) !== ''){
                $this->handleTextNode($child);
            }
            if($child->hasChildNodes()){
                $this->stepThrough($child->childNodes);
            }
        }
    }

    function handleDelimiterIsTag(DOMElement $node): void
    {
        if(Constants::delimiterIsTag() && $node->tagName === substr(Constants::getDelimiter()[0],1,-1)){
            // fake $matches
            $node->nodeValue = $this->replaceVariables([[$node->textContent,$node->textContent,$node->textContent]], $node->textContent);
        }
    }

    /**
     * @param DOMText $node
     * @return void
     */
    function handleTextNode(DOMText $node): void
    {
        // readDelimiter
        $givenValue = $this->readDelimiter($node->nodeValue);
        if($givenValue !== strip_tags($givenValue)){
            $this->appendAsFragment($node, $givenValue);
        } else {
            $node->nodeValue = $this->readDelimiter($node->nodeValue);
            // handle functions
            $this->handleFunctions($node);
        }

    }

    private function appendAsFragment(DOMText $parentNode, string $htmlPartial): void
    {
        $fresh = new \DOMDocument();
        @$fresh->loadHTML( $htmlPartial, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $imported = $parentNode->ownerDocument->importNode($fresh->documentElement, true);
        $parentNode->nodeValue = '';
        $parentNode->parentNode->appendChild($imported);
    }

    /**
     * @param DOMText $element
     * @return void
     */
    function handleFunctions(DOMText $element): void
    {
        foreach (Constants::getCustomFunctions() as $function => $closure){
            $delimiter = Constants::getDelimiter();
            $pattern = "/$delimiter[0]\s*$function\(([^)]*)\)\s*{$delimiter[1]}/";
            $hit = preg_match_all($pattern, $element->nodeValue, $matches, PREG_SET_ORDER);
            if($hit){

                $this->executeFunction($closure, $matches, $element);
            }
        }
    }

    private function executeFunction(callable $callable, $matches, $element): void
    {
        foreach($matches as $match){
            if(!empty($match[1]) && array_key_exists($match[1],$this->flatData)){
                $element->nodeValue = str_replace($match[0], $callable($this->flatData[$match[1]]), $element->nodeValue);
            } elseif (empty($match[1])){
                $element->nodeValue = str_replace($match[0], $callable(), $element->nodeValue);
            } else {
                $element->nodeValue = str_replace($match[0], $callable(...explode(',',$match[1])), $element->nodeValue);
            }
        }
    }


    /**
     * @param DOMElement $element
     * @return void
     */
    function handleAttributes(DOMElement $element): void
    {
        for($i = 0; $i < $element->attributes->count(); $i++){
            $attribute = $element->attributes->item($i);
            // 1. try embrace
            $attribute->nodeValue = $this->readDelimiter($attribute->nodeValue);
            // 2. try custom attributes
            $this->applyCustomAttributes($attribute);
        }
    }

    /**
     * @param DOMAttr $attribute
     * @return void
     */
    function applyCustomAttributes(DOMAttr &$attribute): void
    {
        $attributes = Constants::getCustomAttributes();
        if(isset($attributes[$attribute->name])){
            $attributes[$attribute->name]($attribute, $this->contextData);
        }

    }

    /**
     * @param string $string
     * @return string
     */
    function readDelimiter(string $string): string
    {

        $delimiter = Constants::getDelimiter();
        $pattern = "/({$delimiter[0]}|{$delimiter[2]})([^{$delimiter[1]}|{$delimiter[0]}]+)({$delimiter[1]}|{$delimiter[3]})/";
        $found = @preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

        if($found){
            $string = $this->replaceVariables($matches, $string);
        }
        return $string;
    }

    /**
     * @param array $matches
     * @param string $content
     * @return string
     */
    private function replaceVariables(array $matches, string $content): string
    {
        foreach ($matches as $pair){
            $lookFor = trim($pair[2]);
            $sanitized = preg_match_all('/\[%([^%\]]+)%\]\(%(.+?)(?=%\))%\)/', $lookFor, $preRendered, PREG_SET_ORDER);
            $substitutes = [];
            if($sanitized){
                foreach ($preRendered as $hit){
                    $lookFor = str_replace($hit[0],"[%{$hit[1]}%]", $lookFor);
                    if(isset($hit[2])){
                        $substitutes[] = ["[%{$hit[1]}%]", $hit[2]];
                    }
                }
            }
            if(array_key_exists($lookFor, $this->flatData)){
                $content = str_replace($pair[0], $this->flatData[$lookFor], $content);
                foreach ($substitutes as $substitute){
                    $content = str_replace($substitute[0], $substitute[1], $content);
                }
            }
        }
        return $content;
    }

}