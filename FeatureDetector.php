<?php

class PHPFeatureDetector
{
    private array $issues = [];

    /**
     * Removed/Deprecated functions
     */
    private array $removedFunctions = [

        // mysql
        'mysql_connect'             => 'Removed in PHP 7.0',
        'mysql_pconnect'            => 'Removed in PHP 7.0',
        'mysql_query'               => 'Removed in PHP 7.0',
        'mysql_db_query'            => 'Removed in PHP 7.0',
        'mysql_fetch_array'         => 'Removed in PHP 7.0',
        'mysql_fetch_assoc'         => 'Removed in PHP 7.0',
        'mysql_fetch_object'        => 'Removed in PHP 7.0',
        'mysql_fetch_row'           => 'Removed in PHP 7.0',
        'mysql_num_rows'            => 'Removed in PHP 7.0',
        'mysql_insert_id'           => 'Removed in PHP 7.0',
        'mysql_real_escape_string'  => 'Removed in PHP 7.0',

        // regex
        'ereg'                      => 'Use preg_match()',
        'eregi'                     => 'Use preg_match()',
        'ereg_replace'              => 'Use preg_replace()',
        'eregi_replace'             => 'Use preg_replace()',

        // string
        'split'                     => 'Use explode()',
        'spliti'                    => 'Use preg_split()',

        // misc
        'each'                      => 'Removed in PHP 8',
        'create_function'           => 'Use anonymous functions',
        'call_user_method'          => 'Removed',
        'call_user_method_array'    => 'Removed',
        'get_magic_quotes_gpc'      => 'Removed',
        'set_magic_quotes_runtime'  => 'Removed',
        'get_magic_quotes_runtime'  => 'Removed',
    ];

    public function analyze(string $filename): array
    {
        $this->issues = [];

        $code = file_get_contents($filename);

        $tokens = token_get_all($code);

        foreach ($tokens as $i => $token) {

            if (!is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            switch ($id) {

                case T_STRING:

                    $this->detectRemovedFunction($text, $filename, $line);
                    break;

                case T_VAR:

                    $this->warning(
                        $filename,
                        $line,
                        "Legacy 'var' keyword",
                        "Replace with public/private/protected"
                    );

                    break;

                case T_CLASS:

                    $this->detectOldConstructor($tokens, $i, $filename);

                    break;
            }
        }

        $this->detectCurlyBraces($code, $filename);

        $this->detectAutoload($code, $filename);

        return $this->issues;
    }

    private function detectRemovedFunction($name, $file, $line)
    {
        $name = strtolower($name);

        if (!isset($this->removedFunctions[$name])) {
            return;
        }

        $this->warning(
            $file,
            $line,
            $name,
            $this->removedFunctions[$name]
        );
    }

    private function detectCurlyBraces($code, $file)
    {
        preg_match_all('/\$[A-Za-z0-9_]+\{[^\}]+\}/', $code, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {

            $line = substr_count(substr($code,0,$match[1]),"\n")+1;

            $this->warning(
                $file,
                $line,
                "Curly Brace Offset",
                "Use [] instead."
            );
        }
    }

    private function detectAutoload($code, $file)
    {
        if (preg_match('/function\s+__autoload/i',$code,$m,PREG_OFFSET_CAPTURE)) {

            $line = substr_count(substr($code,0,$m[0][1]),"\n")+1;

            $this->warning(
                $file,
                $line,
                "__autoload()",
                "Use spl_autoload_register()"
            );
        }
    }

    private function detectOldConstructor(array $tokens,int $index,string $file)
    {
        // placeholder
        // compare class name against next function name
        // if they match, it's an old-style constructor
        // ...
        
    }

    private function warning($file,$line,$feature,$message)
    {
        $this->issues[] = [

            'file'=>$file,
            'line'=>$line,
            'feature'=>$feature,
            'message'=>$message

        ];
    }

    public function getIssues(): array
    {
        return $this->issues;
    }
}