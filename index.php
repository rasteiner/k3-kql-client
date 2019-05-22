<?php 

use rasteiner\kql\Interpreter;
use rasteiner\kql\Context;
use Kirby\Exception\Exception;
use Rasteiner\KQL\FunctionList;

class KQL {
    protected static $_blacklist = null;
    protected static $_whitelist = null;

    public static function clientScript() {
        return attr([
            'src' => kirby()->url('media') . '/plugins/rasteiner/kql/client.js',
            'data-csrf' => csrf(),
            'data-ep' => kirby()->url('api') . '/kql',
        ], '<script ', '></script>');
    }

    public static function project($obj, $fields) {
        $projection = [];

        if($obj) {
            $interpreter = new Interpreter(
                new Context(
                    [
                        'this' => $obj
                    ], 
                    self::blacklist(),
                    self::whitelist()
                )
            );

            foreach ($fields as $field => $query) {
                $projection[$field] = $interpreter->parse($query);
            }
        }

        return $projection;
    }


    public static function whitelist()
    {
        if (self::$_whitelist) return self::$_whitelist;

        //this is the hardcoded default whitelist
        $defaultWhitelist = [];

        $whitelist = new FunctionList($defaultWhitelist);

        //merge config whitelist with defaults
        $configWhitelist = option('rasteiner.kql.whitelist', null);
        if($configWhitelist) {
            $whitelist->addArray($configWhitelist);
        }

        return self::$_whitelist = $whitelist;
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

        $blacklist = new FunctionList($blacklist);

        //merge config blacklist with defaults
        $configBlacklist = option('rasteiner.kql.blacklist', []);
        $blacklist->addArray($configBlacklist);

        return self::$_blacklist = $blacklist;
    }
}

load([
    'rasteiner\kql\interpreter' => 'Interpreter.php',
    'rasteiner\kql\context' => 'Interpreter.php',
    'rasteiner\kql\functionlist' => 'Interpreter.php'
], __DIR__ . '/src');

Kirby::plugin('rasteiner/kql', [
    'options' => [
        'override-default-blacklist' => false,
        'blacklist' => [],
        'whitelist' => null,
        'public' => false,
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
                    //should this api be accessible to not logged in visitors? 
                    $public = option('rasteiner.kql.public', false);
                    if(!$public) {
                        if(kirby()->user() === null) {
                            throw new Exception('API not public');
                        }
                    }

                    //check csrf
                    $csrf = $this->requestBody('csrf', 'a');
                    $query = $this->requestBody('query', false);
                    $submittedValues = $this->requestBody('values', []);
                    $values = [];

                    
                    if(!csrf($csrf)) {
                        throw new Exception('Invalid CSRF');                        
                    }
                    if (empty($query)) {
                        throw new Exception('Empty query');
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
                    $whitelist = KQL::whitelist();

                    $context = new Context($myglobals, $blacklist, $whitelist);

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
