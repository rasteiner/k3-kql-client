%lex



%% 

\s+                      /* skip whitespace */
    
\btrue\b                 return 'TRUE'
\bfalse\b                return 'FALSE'
\bnull\b                 return 'NULL'
    
\(                       return 'LPAREN'
\)                       return 'RPAREN'
\[                       return 'LBRACKET'
\]                       return 'RBRACKET'
\,                       return 'COMMA'
\.                       return 'DOT'
\?\?                     return 'COALESCE'

\d*.\d+                  return 'FLOAT'
\d+?                     return 'INT'

[a-zA-Z0-9_]+            return 'IDENTIFIER'

\"(?:[^"\\]++|\\.)*+\"   $yytext = stripcslashes(substr($yytext, 1, -1)); return 'STRING';
\'(?:[^'\\]++|\\.)*+\'   $yytext = substr($yytext, 1, -1); return 'STRING';



/lex



%{
    require 'ast.php';
%}

%% 

Expression
    : CoalesceExpression
    ;

CoalesceExpression
    : IndexExpression
    | CoalesceExpression COALESCE IndexExpression { $$ = new Coalesce($1, $3) }
    ;

IndexExpression
    : MethodExpression
    | IndexExpression LBRACKET Expression RBRACKET { $$ = new Access($1, $3) }
    ;

MethodExpression
    : AccessExpression
    | MethodExpression LPAREN RPAREN { $$ = new Method($1, []) }
    | MethodExpression LPAREN List RPAREN { $$ = new Method($1, $3) }
    ;

AccessExpression
    : AtomicExpression
    | IndexExpression DOT Identifier { $$ = new Access($1, $3) }
    ;

AtomicExpression
    : LPAREN Expression RPAREN { $$ = $2 }
    | Literal
    | Identifier
    ;

Identifier
    : IDENTIFIER { $$ = new Identifier($1) }
    ;

Literal
    : STRING { $$ = new Value($1) }
    | INT { $$ = new Value(intval($1)) }
    | FLOAT { $$ = new Value(floatval($1)) }
    | TRUE { $$ = new Value(true) }
    | FALSE { $$ = new Value(false) }
    | NULL { $$ = new Value(null) }
    | LBRACKET List RBRACKET { $$ = new Value($2) }
    ;

List
    : Expression { $$ = [ $1 ] }
    | List COMMA Expression { $1[] = $3; $$ = $1; }
    ;
