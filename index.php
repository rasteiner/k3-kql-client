<?php 

use rasteiner\kql\Interpreter;
use rasteiner\kql\Context;
use Kirby\Exception\Exception;
use Rasteiner\KQL\Blacklist;

class KQL {
    protected static $_blacklist = null;

    public static function clientScript() {
        return attr([
            'src' => kirby()->url('media') . '/plugins/rasteiner/kql/client.js',
            'data-csrf' => csrf(),
            'data-ep' => kirby()->url('api') . '/kql',
        ], '<script ', '></script>');
    }

    public static function project($obj, $fields) {
        $projection = [];
        $blacklist = self::blacklist();

        if($obj) {
            foreach ($fields as $field => $sub) {
                if(!$blacklist->canAccess($obj, $field)) continue;
                
                $result = $obj->$field();

                if(is_array($sub)) {
                    $projection[$field] = KQL::project($result, $sub);
                } else {
                    if($sub) {
                        $projection[$field] = $result;
                    }
                }
            }
        }

        return $projection;
    }

    public static function blacklist() {
        if(self::$_blacklist) return self::$_blacklist;

        //this is the hardcoded default blacklist
        $defaultBlacklist = [
            'Kirby\\Cms\\User' => ['loginPasswordless'],
            'Kirby\\Cms\\App' => ['impersonate'],
            'Kirby\\Cms\\Model' => ['query', 'drafts', 'childrenAndDrafts', 'toString', 'createNum'],

        ];

        //should the default blacklist be ignored
        $ignoreDefaultBlacklist = option('rasteiner.kql.override-default-blacklist', false);
        $blacklist = $ignoreDefaultBlacklist ? [] : $defaultBlacklist;

        $blacklist = new Blacklist($blacklist);

        //merge config blacklist with defaults
        $configBlacklist = option('rasteiner.kql.blacklist', []);
        foreach ($configBlacklist as $classname => $props) {
            foreach ($props as $prop) {
                $blacklist->addToBlacklist($classname, $prop);
            }
        }

        return self::$_blacklist = $blacklist;
    }
}

load([
    'rasteiner\kql\interpreter' => 'Interpreter.php',
    'rasteiner\kql\context' => 'Interpreter.php',
    'rasteiner\kql\blacklist' => 'Interpreter.php'
], __DIR__ . '/src');

Kirby::plugin('rasteiner/kql', [
    'options' => [
        'override-default-blacklist' => false,
        'blacklist' => [],
        'get-globals' => function() {
            return [
                'site' => site()
            ];
        } 
    ],
    'pagesMethods' => [
        'project' => function($fields) {
            $projection = [];
            foreach ($this as $page) {
                $projection[$page->url()] = $page->project($fields);
            }
            return $projection;
        }
    ],
    'pageMethods' => [
        'project' => function($fields) {
            return KQL::project($this, $fields);
        }
    ],
    'api' => [
        'routes' => [
            [
                'pattern' => 'kql',
                'auth' => false,
                'method' => 'post',
                'action' => function() {
                    //check csrf
                    $csrf = $this->requestBody('csrf', 'a');
                    $query = $this->requestBody('query', false);
                    $submittedValues = $this->requestBody('values', []);
                    $values = [];

                    
                    if(!csrf($csrf)) {
                        throw new Exception();                        
                    }
                    if (empty($query)) {
                        throw new Exception();
                    }

                    //give submitted values a name
                    foreach ($submittedValues as $i => $value) {
                        $values["__$i"] = $value;
                    }

                    //get default globals from options
                    $myglobals = option('rasteiner.kql.get-globals', []);
                    if(is_callable($myglobals)) {
                        $myglobals = ($myglobals)();
                    }

                    //add submitted variables (give priority to defaults)
                    $myglobals = array_replace($values, $myglobals);

                    $blacklist = KQL::blacklist();
                    $context = new Context($myglobals, $blacklist);

                    //create interpreter
                    $interpreter = new Interpreter($context);

                    $result = $interpreter->parse($query);

                    return [
                        'result' => $result
                    ];
                }
            ]
        ]
    ]
]);
