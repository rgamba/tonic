<?php
use PHPUnit\Framework\TestCase;
require_once("src/Tonic.php");
use Tonic\Tonic;

class TonicTest extends TestCase {
    private $vars = array(
        'name' => 'Ricardo',
        'last_name' => 'Gamba',
        'array' => array(
            'one' => 'first item string',
            'two' => 2
        ),
        'json_string' => '{"name" : "Ricardo", "last_name": "Gamba"}',
        'inline_js' => "javascript: alert('Hello');",
        'empty' => '',
        'ten' => 10,
        'users' => array(
            array(
                "name" => "rocio 'lavin'",
                "email" => "rlavin@gmail.com",
                "role" => "admin"
            ),
            array(
                "name" => "roberto lopez",
                "email" => "rlopex@gmail.com",
                "role" => "member"
            ),
            array(
                "name" => "rodrigo gomez",
                "email" => "rgomez@gmail.com",
                "role" => "member"
            )
        )
    );

    public function __construct(){
        $this->vars['object'] = (object)$this->vars['array'];
        $this->vars['date'] = date_create('2016-11-30');
    }

    public function testVariables() {
        $tpl = array(
            array('<p>{$name}</p>', '<p>Ricardo</p>'),
            array('<p>{ $last_name }</p>', '<p>Gamba</p>'),
            array('<p>{$array.one}</p>', '<p>first item string</p>'),
            array('<p>{$object->one}</p>', '<p>first item string</p>'),
        );
        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testModifiers() {
        // TODO: Test all modifiers
        Tonic::extendModifier("myModifier",function($input, $prepend, $append = ""){
            if(empty($prepend)) {
                throw new \InvalidArgumentException("prepend is required");
            }
            return $prepend . $input . $append;
        });
        $tpl = array(
            array('<p>{$name.upper()}</p>', '<p>RICARDO</p>'),
            array('<p>{$array.one.capitalize().truncate(10)}</p>', '<p>First Item...</p>'),
            array('<p>{$empty.default("No value")}</p>', '<p>No value</p>'),
            array('<p>{$date.date("d-m-Y")}</p>', '<p>30-11-2016</p>'),
            array('<p>{$name.myModifier("Mr. ")}</p>', '<p>Mr. Ricardo</p>')
        );
        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testIf() {
        $tpl = array(
            array('{if $name eq "Ricardo"}YES{else}NO{endif}', 'YES'),
            array('{if $name.lower() == "ricardo"}YES{else}NO{endif}', 'YES'),
            array('{  if $name.lower() == "ricardo"  }YES{  else  }NO{  endif  }', 'YES'),
            array('{  if $name == $last_name  }YES{  else  }NO{  endif  }', 'NO')
        );
        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testIfMacros() {
        $tpl = array(
            array('<p tn-if="$name eq \'Ricardo\'">YES</p>', '<p >YES</p>'),
            array('<p tn-if="$name">YES</p>', '<p >YES</p>'),
            array('<p tn-if="!$name">YES</p>', ''),
            array('<p tn-if="$ten >= 20">YES</p>', '')
        );
        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testLoops() {
        $tpl = array(
            array('{loop $i, $user in $users}{$i}:{$user.name}<br>{endloop}', '0:rocio &#039;lavin&#039;<br>1:roberto lopez<br>2:rodrigo gomez<br>'),
            array('{loop $u in $users}{$u.name}<br>{endloop}', 'rocio &#039;lavin&#039;<br>roberto lopez<br>rodrigo gomez<br>')
        );

        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testLoopsMacros() {
        $tpl = array(
            array('<li tn-loop="$i, $user in $users">{$i}:{$user.name}</li>', '<li >0:rocio &#039;lavin&#039;</li><li >1:roberto lopez</li><li >2:rodrigo gomez</li>'),
            array('<li tn-loop="$u in $users">{$u.name}</li>', '<li >rocio &#039;lavin&#039;</li><li >roberto lopez</li><li >rodrigo gomez</li>')
        );

        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }

    public function testContextAwareness() {
        $tpl = array(
            array('<h1 data-attr="{$inline_js}">Test</h1>', '<h1 data-attr="javascript%3A+alert%28%27Hello%27%29%3B">Test</h1>'),
            array('<pre>{$inline_js}</pre>', '<pre>javascript: alert(&#039;Hello&#039;);</pre>'),
            array('<a href="{$inline_js}">Click</a>', '<a href="javascript%3A+alert%28%27Hello%27%29%3B">Click</a>'),
            array('<a href="?{$array}">Link</a>', '<a href="?one=first+item+string&two=2">Link</a>'),
            array('<p>{$inline_js.ignoreContext()}</p>', '<p>javascript: alert(\'Hello\');</p>')
        );

        foreach($tpl as $i => $t) {
            $template = new Tonic();
            $template->loadFromString($t[0])->setContext($this->vars);
            $this->assertEquals($t[1], $template->render());
        }
    }
}