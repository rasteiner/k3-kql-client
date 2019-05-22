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

class FunctionList {
    /**
     * @var array 
     */
    public $list = [];

    public function __construct(array $list) {
        $this->addArray($list);
    }

    public function isEmpty() {
        return count($this->list) === 0;
    }

    public function add($parent, $property) {
        $parent = strtolower($parent);
        $property = strtolower($property);

        $this->list[$parent][$property] = 1;
    }

    public function addArray(array $arr) {
        foreach ($arr as $classname => $props) {
            foreach ($props as $prop) {
                $this->add($classname, $prop);
            }
        }
    }

    public function has($parent, $property)
    {
        $property = strtolower($property);

        foreach ($this->list as $classname => $deniedProperties) {
            if (is_a($parent, $classname)) {
                if (key_exists($property, $deniedProperties)) {
                    return true;
                }
            }
        }

        return false;
    }

}
 
class Context {
    /**
     * @var array 
     */
    public $globals = null;

    /**
     * @var FunctionList 
     */
    public $blacklist = null;

    /**
     * @var FunctionList 
     */
    public $whitelist = null;

    public function __construct($globals = [], $blacklist = [], $whitelist = []) {
        $this->globals = $globals;
        if(is_a($blacklist, "rasteiner\\kql\\functionlist")) {
            $this->blacklist = $blacklist;
        } else {
            $this->blacklist = new FunctionList($blacklist);
        }

        if (is_a($whitelist, "rasteiner\\kql\\functionlist")) {
            $this->whitelist = $whitelist;
        } else {
            $this->whitelist = new FunctionList($whitelist);
        }
    }

    public function canAccess($parent, $property) {
        if(!$this->whitelist->isEmpty()) {
            if(!$this->whitelist->has($parent, $property)) {
                return false;
            }
        }

        return false === $this->blacklist->has($parent, $property);
    }
}
