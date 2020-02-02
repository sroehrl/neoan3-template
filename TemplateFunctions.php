<?php


namespace Neoan3\Apps;


class TemplateFunctions
{
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
            // extract attribute
            $parts = explode(' ', $hit->getAttribute('n-for'));
            // remove attribute
            $hit->removeAttribute('n-for');
            // while string
            $template = Template::nodeStringify($hit);

            // clean
            foreach ($parts as $i => $part) {
                if (empty(trim($part))) {
                    unset($parts[$i]);
                }
            }
            $parts = array_values($parts);
            $newContent = '';
            if (isset($array[$parts[0]]) && !empty($array[$parts[0]])) {
                $subArray = [];
                foreach ($array[$parts[0]] as $key => $value) {
                    if (isset($parts[4])) {
                        $subArray[$parts[2]] = $key;
                        $subArray[$parts[4]] = $value;
                    } else {
                        $subArray[$parts[2]] = $value;
                    }
                    $newContent .= Template::embrace($template, $subArray);
                }
                Template::clone($doc, $hit, $newContent);
            }
        }
        return $doc->saveHTML();
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
            $bool = true;
            $array = Template::flattenArray($array);
            foreach ($array as $key => $value) {
                if (strpos($expression, $key) !== false) {
                    switch (gettype($array[$key])) {
                        case 'boolean':
                            $expression = str_replace($key, $array[$key] ? 'true' : 'false', $expression);
                            break;
                        case 'NULL':
                            $expression = str_replace($key, 'false', $expression);
                            break;
                        case 'string':
                            $expression = str_replace($key, '"' . $array[$key] . '"', $expression);
                            break;
                        default:
                            $expression = str_replace($key, $array[$key], $expression);
                            break;
                    }
                    $bool = eval("return $expression;");
                }
            }

            if (!$bool) {
                $hit->parentNode->removeChild($hit);
            } else {
                $hit->removeAttribute('n-if');
            }
        }
        return $doc->saveHTML();
    }
}