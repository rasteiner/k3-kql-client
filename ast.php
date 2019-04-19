<?php
namespace Rasteiner\KQLParser;


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
        return $this->right->eval($context, $left);
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
    public $name = null;
    public $arguments = null;
    public function __construct($name, $arguments) {
        parent::__construct('Method');
        $this->name = $name;
        $this->arguments = $arguments;
    }
    public function eval(Evaluator $context, $parent=null) {
        if(!$parent) {
            throw new Exception("Methods cannot be called on Null", 1);
        }

        $params = $context->eval($this->arguments);

        if(is_array($parent)) {
            return call_user_func_array($parent[$this->name], $params);
        } else {
            return call_user_func_array([$parent, $this->name], $params);
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
        if (!$parent) {
            if($val = $context->fetchGlobal($this->name)) {
                return $val;
            }

            throw new Exception("$this->name not found", 1);
        } else {
            if(is_array($parent)) {
                if(isset($parent[$this->name])) {
                    return $parent[$this->name];
                }
                return null;
            } else if(is_object($parent)) {
                if(property_exists($parent, $this->name)) {
                    return $this->{$this->name};
                }
                return call_user_func_array([$parent, $this->name], []);
            }
        }
    }
}