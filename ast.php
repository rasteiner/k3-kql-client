<?php
namespace Rasteiner\KQLParser;
use \Exception;

abstract class Node {
    public $type;

    public function __construct($type) {
        $this->type = $type;
    }

    abstract public function eval(Evaluator $context, $parent=null);
}


class AccessNode extends Node {
    public $left = null;
    public $right = null;
    public function __construct($left, $right) {
        parent::__construct('Access');
        $this->left = $left;
        $this->right = $right;
    }
    public function eval(Evaluator $context, $parent=null) {
        $left = $this->left->eval($context);
        if(is_callable($left)) {
            $left = ($left)();
        }

        if($this->right instanceof Node) {
            $right = $this->right->eval($context, $this);
        } else if (is_string($this->right)) {
            $right = $this->right;
        } else {
            throw new Exception("Don't know how to use right side of Access Node", 1);
        }

        if (is_array($left)) {
            if (isset($left[$right])) {
                return $left[$right];
            }
            return null;
        } else if (is_object($left)) {
            if (property_exists($left, $right)) {
                return $left->{$right};
            } else if(method_exists($left, $right)) {
                return \Closure::fromCallable([$left, $right]);
            }
        }
    }
}

class NullCoalesceNode extends Node {
    public $left = null;
    public $right = null;
    public function __construct($left, $right) {
        parent::__construct('NullCoalesce');
        $this->left = $left;
        $this->right = $right;
    }
    public function eval(Evaluator $context, $parent=null) {
        $left = null;

        try {
            $left = $this->left->eval($context);
        } catch (Exception $e) {
            //silently fail left evaluation
        }
        
        if($left) {
            return $left;
        } else {
            if($this->right instanceof Node) {
                return $this->right->eval($context);
            } elseif(is_array($this->right)) {
                return $context->eval($this->right);
            } else {
                throw new Exception("Unexpected value $this->right", 1);
            }
        }
    }
}

class ValueNode extends Node {
    public $value = null;
    public function __construct($value) {
        parent::__construct('Value');
        $this->value = $value;
    }
    public function eval(Evaluator $context, $parent=null) {
        return $this->value;
    }
}

class MethodNode extends Node {
    public $method = null;
    public $arguments = null;
    public function __construct($method, $arguments) {
        parent::__construct('Method');
        $this->method = $method;
        $this->arguments = $arguments;
    }
    public function eval(Evaluator $context, $parent=null) {
        $method = $this->method->eval($context, $this);
        $params = $context->eval($this->arguments);

        if(is_callable($method)) {
            return call_user_func_array($method, $params);  
        } else  {
            echo "method:";
            var_dump($method);
            throw new Exception("Method not callable, $method", 1);
        }

    }
}

class SymbolNode extends Node {
    public $name = null;
    public function __construct($name) {
        parent::__construct('Symbol');
        $this->name = $name;
    }
    public function eval(Evaluator $context, $parent=null) {

        if($parent === null && $val = $context->fetchGlobal($this->name)) {
            return $val;
        }

        return $this->name;
    }
}