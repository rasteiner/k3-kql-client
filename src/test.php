<?php 

require __DIR__ . '/Interpreter.php';

use Rasteiner\KQL\Interpreter;
use Rasteiner\KQL\Context;

class Cat {
    private $_age = 12;
    public $name = null;

    function __construct($name = 'Fluffy') {
        $this->name = $name;
    }

    function say($what = 'meow') {
        return $what;
    }
    function age() {
        return $this->_age;
    }
    function makeKitten() {
        $kitten = new Cat($this->name . '\'s Kitten');
        $kitten->_age = 1;
        return $kitten;
    }
}

function testFail($q) {
    // here I give the evaluator a state to work on. These are the allowed starting variables. 
    $interpreter = new Interpreter(new Context([
        'number1' => 1000,

        'site' => [
            'foobar' => [
                'cat' => new Cat()
            ],
            'sum' => function ($a = 1, $b = 1) {
                return $a + $b;
            },
            'sumAll' => function (array $numbers) {
                return array_reduce($numbers, function ($all, $one) {
                    return $all + $one;
                }, 0);
            }
        ]
    ]));

    $failed = false;
    try {
        $result = $interpreter->parse($q);
    } catch(\Throwable $th) {
        $failed = true;
    }


    if ($failed) {
        echo "$q: ✅";
    } else {
        echo "$q: ❌\n";
        echo "should have failed, but returned";
        var_dump($result);
        exit;
    }
    echo "\n";

}


function test($q, $expected) {
    // here I give the evaluator a state to work on. These are the allowed starting variables. 
    $interpreter = new Interpreter(new Context([
        'number1' => 1000,

        'site' => [
            'foobar' => [
                'cat' => new Cat()
            ],
            'sum' => function ($a = 1, $b = 1) {
                return $a + $b;
            },
            'sumAll' => function (array $numbers) {
                return array_reduce($numbers, function ($all, $one) {
                    return $all + $one;
                }, 0);
            }
        ]
    ]));

    $result = $interpreter->parse($q);
    if($result == $expected) {
        echo "$q: ✅";
    } else {
        echo "$q: ❌\n";
        echo "returned: ";
        var_dump($result);
        echo "\nexpected: ";
        var_dump($expected);
        exit;
    }
    echo "\n";
}

test('[1000, 2000, 3000][2]', 3000);

//accessing and running stuff
test('site.sum(number1, site.sum(12, site.foobar.cat.age))', 1024);

//accessing return value of methods
test('site.sum(site.foobar.cat.age, site.foobar.cat.makeKitten().age)', 13);

//access result of implicit function call
test('site.foobar.cat.makeKitten.age', 1);

//accessing properties
test('site.foobar.cat.name', "Fluffy");

//strings 
test('site.foobar.cat.say("hello \"world\"")', 'hello "world"');

//strings with escape chars
test('site.foobar.cat.say("hello\nworld")', "hello\nworld");

//strings with escaped escape chars
test('site.foobar.cat.say("hello\\\\nworld")', 'hello\nworld');

//strings with ignored escape chars
test('site.foobar.cat.say(\'hello\nworld\')', 'hello\nworld');


// arrays
test('site.sumAll([1, 2, 3])', 6);

// complex arrays
test('site.sumAll([number1, site.sum(1000, site.foobar.cat.age), 12])', 2024);

// implicit method calling on objects
test('site.foobar.cat.say', "meow");

// test simple parentheses
test('(site.foobar).cat.say("simple parens")', "simple parens");

// test complex parentheses
test('((site.foobar).cat.say)("complex parens")', "complex parens");

// simple null coalescing operator [find first]
test('site.foobar.cat.say("nco1") ?? blah', "nco1");

// simple null coalescing operator [find last]
test( 'site.notexisting ?? site.foobar.cat.say("nco2")', "nco2");

// multiple null coalescing operator [find first]
test( 'site.foobar.cat.say("nco3") ?? blah ?? blub', "nco3");

// multiple null coalescing operator [find middle]
test( 'site.notexisting ?? site.foobar.cat.say("nco4") ?? site.stillnotexisting', "nco4");

// multiple null coalescing operator [find last]
test( 'site.notexisting ?? notreally.there ?? site.foobar.cat.say("nco5")', "nco5");

// empty string, 0 and false are considered null
test('"" ?? 0 ?? false ?? "nco6"', "nco6");

// fallback to literal number
test('site.notexisting ?? 42', 42);

// fallback to literal string
test('site.notexisting ?? "string"', "string");

// fallback to literal array
test('site.notexisting ?? [42, "string", true, site.foobar.cat.say, site.foobar.cat.say("foobar")]', 
    [42, "string", true, "meow", "foobar"]
);

// complex null coalescing example
test('(site.foobar.cat.notexisting ?? site.foobar.cat.say)(site.foobar.cat.name)', "Fluffy");

//should not allow access to php functions
testFail('strtoupper("upper")');

// access fallback literal array (also numeric indexes)
test(
    '(site.notexisting ?? [42, "string", true, site.foobar.cat, site.foobar.cat.say("foobar")])[3].say',
    "meow"
);