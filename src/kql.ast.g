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
            [ "\\?\\?", "return 'COALESCE'" ],
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
    "moduleInclude": `require 'ast.php';`,
    "bnf": {
        "E" : [
            ["TERM", "$$ = $1"],
            ["E COALESCE TERM", "$$ = new NullCoalesceNode($1, $3)"],
        ],
        "TERM": [
            ["PRIMARY", "$$ = $1"],
            ["TERM . PRIMARY", "$$ = new AccessNode($1, $3)"],
            ["TERM ( )", "$$ = new MethodNode($1, [])"],
            ["TERM ( LIST )", "$$ = new MethodNode($1, $3)"],
        ],
        "PRIMARY": [
            ["SYMBOL", "$$ = new SymbolNode($1)"],
            ["LITERAL", "$$ = $1"],
            ["( E )", "$$ = $2"],
        ],
        "LIST": [
            ["VALUE", "$$ = [$1]"],
            ["LIST , VALUE", "$1[] = $3; $$ = $1"],
        ],
        "VALUE": [
            ["E", "$$ = $1"],
            ["SYMBOL", "$$ = new SymbolNode($1)"],
            ["LITERAL", "$$ = $1"]
        ],
        "LITERAL": [
            ["STRING", "$$ = new ValueNode($1)"],
            ["NUMBER", "$$ = new ValueNode(floatval($1))"],
            ["TRUE", "$$ = new ValueNode(true)"],
            ["FALSE", "$$ = new ValueNode(false)"],
            ["NULL", "$$ = new ValueNode(null)"],
            ["[ LIST ]", "$$ = $2"]
        ],
    }
}