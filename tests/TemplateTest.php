<?php


namespace Neoan3\Apps\Tests;

use Neoan3\Apps\Template\Constants;
use Neoan3\Apps\Template\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{


    public function setUp(): void
    {
        Constants::setDelimiter('{{','}}');
        Constants::setPath(dirname(__FILE__));
    }

    public function testEmbrace()
    {
        $str = '<div>{{easy}}-{{deep.value}}</div>';
        $sub = ['easy' => 'a', 'deep' => ['value' => 'b']];
        $this->assertSame('<div>a-b</div>', Template::embrace($str, $sub));
    }

    public function testEmbraceSanitation()
    {
        $array = [
            's/t' => 1, 's\t' => 2, 's+t' => 3, 's-t' => 4, 's{t' => 1
        ];
        $template = '<div><p n-for="array as key => item">{{item}}</p></div>';
        $res = '<div>';
        foreach ($array as $item){
            $res .= '<p>'. $item .'</p>';
        }
        $res .= '</div>';
        $this->assertSame($res, trim(Template::embrace($template, ['array' =>$array])));
    }

    public function testFlattenArray()
    {
        $testArray = ['one' => ['some' => 'value'], 'two' => ['item1', 'item2']];
        $shouldBe = [
            'one' => 'Array',
            'one.length' => 1,
            'one.some' => 'value',
            'two' => 'Array',
            'two.length' => 2,
            'two.0' => 'item1',
            'two.1' => 'item2'
        ];
        $this->assertSame($shouldBe, Constants::flattenArray($testArray));
    }

    public function testTEmbrace()
    {
        $str = 'any <t>how</t>';
        $sub = ['how' => 'test'];
        Constants::setDelimiter('<t>','</t>');
        $this->assertSame('any <t>test</t>', Template::embrace($str, $sub));
    }


    public function testEmbraceFromFile()
    {
        $sub = ['easy' => 'a', 'deep' => ['value' => 'b']];
        $t = Template::embraceFromFile('embrace.html', $sub);
        $this->assertSame('<div>a-b</div>', trim($t));
    }

    public function testConditional()
    {
        $str = '<custom-ele n-if="testKey">out</custom-ele>';
        $sub = ['testKey' => false];
        $this->assertEmpty(trim(Template::embrace($str, $sub)));
        $sub['testKey'] = true;
        $this->assertStringContainsString('custom-ele', Template::embrace($str, $sub));
    }

    public function testComplexEmbrace()
    {
        $array = ['items' => ['one', 'two'], 'sub' => [1, 2, 3], 'deeps' => ['one' => 1, 'two' => 2]];
        $t = Template::embraceFromFile('embraceComplex.html', $array);
        $this->assertStringContainsString('<li>1</li><li>2</li>', $t);
        $this->assertIsString($t);
    }

    public function testNestedCondition()
    {
        $array = ['deeps' => [
            ['number' => 3],
            ['number' => 2]
        ]];
        $t = Template::embraceFromFile('nestedCondition.html', $array);
        $this->assertStringContainsString('exists', $t);
        $this->assertStringNotContainsString('not', $t);
    }

    public function testEmbraceTypes()
    {
        $array = ['string' => 'String', 'number' => 2, 'boolean' => true, 'falseExpression' => false];
        $t = Template::embraceFromFile('typeTest.html', $array);
        $expectedResult = ['String', 'Boolean', 'yes'];
        foreach ($expectedResult as $true) {
            $this->assertStringContainsString($true, $t);
        }
        // cross-check
        $this->assertStringNotContainsString('no', $t);
    }

    public function testCallback()
    {
        $array = [
            'some' => 'value'
        ];
        Constants::addCustomFunction('myFunc',function($x){
            return strtoupper($x);
        });
        $t = Template::embraceFromFile('callback.html', $array);

        $this->assertStringContainsString('<p>VALUE</p>', $t);
        $this->assertStringContainsString('<li>show me</li>', $t);
    }
    public function testNoCallback()
    {
        $array = [
            'items' => ['one', 'two'],
            'som' => 'value'
        ];
        Constants::addCustomFunction('myFunc',function($x) {
            return $x . '-shouldnt';
        });
        $t = Template::embraceFromFile('callback.html', $array);
        $this->assertStringContainsString('myFunc(some)',$t);

    }

    public function testCallbackDeep()
    {
        $array = [
            'items' => ['one', 'two'],
            'some' => 'value'
        ];
        Constants::addCustomFunction('deepFunc',function($input){
            return $input . '!';
        });
        Constants::addCustomFunction('myFunc',function ($x) {
            return strtoupper($x);
        });

        $t = Template::embraceFromFile('callback.html', $array);
        $this->assertStringContainsString('one!', $t);
        $this->assertStringContainsString('VALUE', $t);

    }

    public function testEvaluateTypedConditionNull()
    {
        $array = ['test' => null];
        $t = Template::embrace('<p n-if="test === false">some</p>', $array);
        $this->assertStringNotContainsString('p>some', $t);
    }
    public function testUtf8()
    {
        $array = ['one'=> 'über','keß'=>'plunder'];
        Constants::setEncoding('utf-8');
        $t = Template::embraceFromFile('utf8.html', $array);
        $this->assertStringContainsString('show', $t);
        $t = Template::embraceFromFile('utf8.html', $array);
        $this->assertStringContainsString('plunder', $t);
    }
    public function testEmptyFunction()
    {
        Constants::addCustomFunction('test', fn() => 'here');
        $html = '<p n-if="test()">{{test()}}</p>';
        $t = Template::embrace($html, []);
        $this->assertStringContainsString('here',$t);
    }
}
