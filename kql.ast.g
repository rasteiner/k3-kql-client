{
    "lex": {
        "macros": {
            "digit": "[0-9]",
            "esc": "\\\\",
            "int": "-?(?:[1-9][0-9]+|[0-9])",
            "exp": "(?:[eE][-+]?[0-9]+)",
            "frac": "(?:\\.[0-9]+)"
        },
        "rules": [
            [ "\\s+", "/* skip whitespace */" ],
            [ "true\\b", "return 'TRUE'" ],
            [ "false\\b", "return 'FALSE'" ],
            [ "null\\b", "return 'NULL'" ],
            [ "[a-z_][0-9a-z_]*", "return 'SYMBOL'" ],
            [ "\\.", "return '.'" ],
            [ "{int}{frac}?{exp}?\\b", "return 'NUMBER';" ],
            [ '"(?:[^"{esc}{esc}]++|{esc}{esc}.)*+"', "$yytext = stripcslashes(substr($yytext, 1, -1)); return 'STRING';" ],
            [ "\\'(?:[^\\'{esc}{esc}]++|{esc}{esc}.)*+\\'", "$yytext = substr($yytext, 1, -1); return 'STRING';" ],
            [ "\\[", "return '['" ],
            [ "\\]", "return ']'" ],
            [ "\\(", "return '('" ],
            [ "\\)", "return ')'" ],
            [ ",", "return ','" ]
        ],
        "options": {
            "case-insensitive": true,
        }
    },
    "operators": [
        ["left", "."]
    ],
    "moduleInclude": `require 'ast.php';`,
    "bnf": {
        "E" : [
            ["E . E", "$$ = new AccessNode($1, $3)"],
            ["SYMBOL", "$$ = new SymbolNode($1)"],
            ["SYMBOL ( )", "$$ = new MethodNode($1, [])"],
            ["SYMBOL ( LIST )", "$$ = new MethodNode($1, $3)"],
        ],
        "VALUE": [
            ["E", "$$ = $1"],
            ["STRING", "$$ = new ValueNode($1)"],
            ["SYMBOL", "$$ = new SymbolNode($1)"],
            ["NUMBER", "$$ = new ValueNode(floatval($1))"],
            ["TRUE", "$$ = new ValueNode(true)"],
            ["FALSE", "$$ = new ValueNode(false)"],
            ["NULL", "$$ = new ValueNode(null)"],
            ["[ LIST ]", "$$ = $2"]
        ],
        "LIST": [
            ["VALUE", "$$ = [$1]"],
            ["LIST , VALUE", "$1[] = $3; $$ = $1"]
        ]
    }
}