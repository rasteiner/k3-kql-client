<?php

namespace Rasteiner\KQLParser;

require 'Parser.php';

class Interpreter
{
    /**
     * @var Evaluator
     */
    private $ev;

    /**
     * @var Parser
     */
    private $parser;

    public function __construct(array $globals = [])
    {
        $this->ev = new Evaluator($globals);
        $this->parser = new Parser();
    }

    public function parse($text)
    {
        $ast = $this->parser->parse($text);
        
        return $this->ev->eval($ast);
    }
}


class Evaluator
{
    private $globals = null;

    public function __construct(array $globals = [])
    {
        $this->globals = $globals;
    }

    public function eval($node)
    {
        if (is_array($node)) {
            return array_map(function ($node) {
                return Evaluator::eval($node);
            }, $node);
        } else {
            $return = $node->eval($this);
            if (is_callable($return)) {
                return ($return)();
            } else {
                return $return;
            }
        }
    }

    public function fetchGlobal($name)
    {
        if (isset($this->globals[$name])) {
            return $this->globals[$name];
        } else {
            return null;
        }
    }
}
