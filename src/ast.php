<?php

namespace Rasteiner\KQL;

use Exception;

abstract class Node {
    public abstract function eval(Context $context, $parent = null);

    public static function recursiveEval(Context $context, $nodes) {
        if(is_array($nodes)) {

            $values = [];
            foreach ($nodes as $node) {
                $values[] = self::recursiveEval($context, $node);
            }
            return $values;

        } else {

            $e = $nodes->eval($context);
            if (is_a($e, 'Closure')) {
                $e = ($e)();
            }
            return $e;

        }
    }
}

class Projection extends Node {
    /**
     * @var Node $left
     */
    protected $left = null;

    /**
     * @var array $right
     */
    protected $right = null;

    public function __construct(Node $left, array $right) {
        $this->left = $left;
        $this->right = $right;
    }

    public function eval(Context $context, $parent = null) {
        $left = $this->left->eval($context);
        $result = []; 
        foreach ($this->right as $id) {
            try {
                $result[$id] = Access::access($context, $left, $id);
            } catch (\Throwable $th) { }
        }
    }
}

class Coalesce extends Node {
    /**
     * @var Node $left
     */

    protected $left = null;
    /**
     * @var Node $right
     */
    protected $right = null;

    public function __construct(Node $left, Node $right) {
        $this->left = $left;
        $this->right = $right;
    }

    public function eval(Context $context, $parent = null) {
        try {
            $left = $this->left->eval($context);
        } catch (\Throwable $th) {
            $left = false;
        }

        if($left) {
            if( !(
                is_array($left) && count($left) == 0
                || is_a($left, 'Kirby\\Toolkit\\Iterator') && $left->count() == 0
                || is_a($left, 'Kirby\\Cms\\Field') && $left->isEmpty()
            )) {
                return $left;
            }
        }

        return $this->right->eval($context);
    }
}


class Method extends Node
{
    protected $method = null;
    protected $params = null;

    public function __construct(Node $method, $params = [])
    {
        $this->method = $method;
        $this->params = $params;
    }

    public function eval( Context $context, $parent = null)
    {
        $method = $this->method->eval($context, $parent);
        $params = self::recursiveEval($context, $this->params);

        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        } else {
            throw new Exception("Not a function", 1);
        }
    }
}


class Access extends Node {
    /**
     * @var Node $left
     */
    protected $left = null;

    /**
     * @var Node $right
     */
    protected $right = null;

    public function __construct(Node $left, Node $right) {
        $this->left = $left;
        $this->right = $right;
    }

    public static function access($context, $left, $id) {
        if (!is_array($left) && !$context->canAccess($left, $id)) {
            throw new Exception("Cannot access $id", 1);
        }

        if (
            is_a($left, 'Kirby\\Cms\\Collection') && is_int($id)
        ) {
            return $left->nth($id);
        } elseif (
            is_a($left, 'Kirby\\Cms\\Field')
            || is_a($left, 'Kirby\\Cms\\Model')
            || is_a($left, 'Kirby\\Cms\\Collection')
            || method_exists($left, $id)
        ) {
            return \Closure::fromCallable([$left, $id]);
        } elseif (is_array($left)) {
            if (key_exists($id, $left)) {
                return $left[$id];
            } else {
                throw new Exception("Undefined array index", 1);
            }
        } elseif (property_exists($left, $id)) {
            return $left->{$id};
        } else {
            //just throw a method call at it
            return \Closure::fromCallable([$left, $id]);
        }
    }

    public function eval( Context $context, $parent = null) {
        $left = $this->left->eval($context);
        if(is_a($left, 'Closure')) {
            $left = ($left)();
        }

        $id = $this->right->eval($context, $left);

        return self::access($context, $left, $id);
    }
}

class Identifier extends Node {
    protected $id = null;

    public function __construct(string $id) {
        $this->id = $id;
    }

    public function eval( Context $context, $parent = null) {
        if($parent) {
            return $this->id;
        } else {
            if(key_exists($this->id, $context->globals)) {
                return $context->globals[$this->id];
            } else {
                throw new Exception("Unknown variable $this->id", 1);
            }
        }

        return null;
    }
}

class Value extends Node {
    protected $value = null;

    public function __construct($value) {
        $this->value = $value;
    }

    public function eval(Context $context, $parent = null) {
        if(is_array($this->value)) {
            return self::recursiveEval($context, $this->value);
        } else {
            return $this->value;
        }
    }
}