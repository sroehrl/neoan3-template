<?php

namespace Neoan3\Apps\Template\Attributes;

interface DomAttribute
{
    function __invoke(\DOMAttr &$attr, $contextData = []): void;
}