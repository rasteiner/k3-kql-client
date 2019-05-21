<?php
namespace Rasteiner\KQL;

require __DIR__ . '/Parser.php';

class Interpreter
{
    /**
     * @var array
     */
    private static $astCache = [];

    /**
     * @var Context $context
     */
    private $context = null;

    
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->parser = new Parser();
    }

    public function parse($text)
    {
        if (isset(self::$astCache[$text])) {
            $ast = self::$astCache[$text];
        } else {
            $ast = $this->parser->parse($text);
            self::$astCache[$text] = $ast;
        }

        $result = $ast->eval($this->context);
        if(is_a($result, 'Closure')) {
            return ($result)();
        } else {
            return $result;
        }
    }
}

class Blacklist {
    /**
     * @var array 
     */
    public $blacklist = [];

    public function __construct(array $blacklist)
    {
        foreach ($blacklist as $classname => $class) {
            foreach ($class as $property) {
                $this->addToBlacklist($classname, $property);
            }
        }
    }

    public function addToBlacklist($parent, $property)
    {
        $parent = strtolower($parent);
        $property = strtolower($property);

        $this->blacklist[$parent][$property] = 1;
    }

    public function canAccess($parent, $property)
    {
        $property = strtolower($property);

        foreach ($this->blacklist as $classname => $deniedProperties) {
            if (is_a($parent, $classname)) {
                if (key_exists($property, $deniedProperties)) {
                    return false;
                }
            }
        }

        return true;
    }

}

class Context {
    /**
     * @var array 
     */
    public $globals = null;

    /**
     * @var Blacklist 
     */
    public $blacklist = null;

    public function __construct($globals = [], $blacklist = []) {
        $this->globals = $globals;
        if(is_a($blacklist, "rasteiner\\kql\\blacklist")) {
            $this->blacklist = $blacklist;
        } else {
            $this->blacklist = new Blacklist($blacklist);
            
        }
    }

    public function addToBlacklist($parent, $property) {
        return $this->blacklist->addToBlacklist($parent, $property);
    }

    public function canAccess($parent, $property) {
        return $this->blacklist->canAccess($parent, $property);
    }
}
