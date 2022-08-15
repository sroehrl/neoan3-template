<?php
namespace Neoan3\Apps\Tests;

use Neoan3\Apps\Template\Constants;
use PHPUnit\Framework\TestCase;

class Test extends TestCase
{
    function testAutoPath()
    {
        $this->assertSame(dirname(__DIR__, 4),Constants::getPath());
    }
}
