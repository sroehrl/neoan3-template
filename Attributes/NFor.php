<?php

namespace Neoan3\Apps\Attributes;

use Neoan3\Apps\Interpreter;

class NFor
{
    function __invoke(\DOMAttr &$attr, $contextData = []): void
    {
        $local = $attr->nodeValue;
        $split = explode(' as ', $local);
        $clone = $attr->parentNode->cloneNode(true);
        $clone->removeAttribute($attr->name);

        if (isset($contextData[$split[0]]) && $contextData[$split[0]] === 'Array') {

            $sub = explode(' ', $split[1]);
            foreach ($sub as $key => $value){
                $sub[$key] = trim($value);
            }
            $isKeyValue = count($sub) > 1;
            for ($i = 0; $i < $contextData[$split[0] . '.length']; $i++) {
                $newContext = $this->createNewContext($contextData[$split[0] . '.'.$i], $sub, $i);

                $newDoc = new Interpreter($attr->ownerDocument->saveHTML($clone),$newContext, true);
                $fresh = new \DOMDocument();
                $fresh->loadHTML($newDoc->asHtml(), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $imported = $attr->ownerDocument->importNode($fresh->documentElement, true);
                $attr->parentNode->parentNode->appendChild($imported);

            }
            // remove original
            $attr->parentNode->parentNode->removeChild($attr->parentNode);

        }
    }

    private function createNewContext($value, $as, $iterator): array
    {
        $newContext = [];
        if(count($as) > 1){
            $newContext[$as[0]] = $iterator;
        }
        $newContext[end($as)] = $value;
        return $newContext;
    }
}