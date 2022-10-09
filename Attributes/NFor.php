<?php

namespace Neoan3\Apps\Template\Attributes;

use Neoan3\Apps\Template\Constants;
use Neoan3\Apps\Template\Interpreter;

class NFor implements DomAttribute
{
    private array $contextData;

    private array $currentDeclaration;

    private string $currentParentKey;

    private array $currentContext;
    private array $flatData;

    private function separateExpression(string $exp):?int
    {
        $split = explode(' as ', $exp);
        $processable = isset($this->flatData[$split[0]]) && $this->flatData[$split[0]] === 'Array';
        if(!$processable){
            return null;
        }
        //current parent
        $this->currentParentKey = $split[0];
        // current declaration
        $this->createCurrentDeclaration(explode(' ', $split[1]));
        return $this->flatData[$split[0].'.length'];
    }

    private function createCurrentDeclaration(array $parts):void
    {
        foreach ($parts as $key => $value){
            $parts[$key] = trim($value);
        }
        $this->currentDeclaration = $parts;
    }

    private function generateCurrentContext(int $iterator):void
    {
        $newContextCandidate = $this->contextData[$this->currentParentKey];
        $key = array_keys($newContextCandidate)[$iterator];

        if(!is_numeric($key)){
            $this->assocContext($key, $newContextCandidate[$key]);
        } elseif(is_array($newContextCandidate[$key])) {
            $this->sequentialContext($key, $newContextCandidate[$key]);
        } else {
            $this->contextData[$this->currentDeclaration[0]] = $key;
            $this->contextData[end($this->currentDeclaration)] = $newContextCandidate[$key];
        }

    }
    private function sequentialContext(string $key, array $value): void
    {

        if(count($this->currentDeclaration)>1){
            $this->contextData[$this->currentDeclaration[0]] = $key;
        }
        $this->contextData[end($this->currentDeclaration)] = $value;
    }
    private function assocContext(string $key, mixed $value):void
    {

        $keyName = $this->currentDeclaration[0];
        $valueName = end($this->currentDeclaration);
        $this->contextData[$keyName] = $key;
        $this->contextData[$valueName] = $value;
    }


    function __invoke(\DOMAttr &$attr, $contextData = []): void
    {
        $this->contextData = $contextData;
        $this->flatData = Constants::flattenArray($contextData);

        $clone = $attr->parentNode->cloneNode(true);
        $clone->removeAttribute($attr->name);

        if ($iterations = $this->separateExpression($attr->nodeValue)) {

            for ($i = 0; $i < $iterations; $i++) {
                $this->generateCurrentContext($i);
                $newDoc = new Interpreter($attr->ownerDocument->saveHTML($clone), $this->contextData, true);
                $html = $newDoc->asHtml();
                if(!empty(trim($html))){
                    $fresh = new \DOMDocument();
                    @$fresh->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                    $imported = $attr->ownerDocument->importNode($fresh->documentElement, true);
                    $attr->parentNode->parentNode->appendChild($imported);
                }

            }
            // remove original
            $attr->parentNode->parentNode->removeChild($attr->parentNode);

        }
    }


}
