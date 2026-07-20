<?php

class PHPUpgrader
{
    protected string $projectPath;
    protected array $extensions = ['php'];
    protected bool $backup = true;

    protected array $replacements = [

        // mysql
        '/mysql_query\s*\(/i'       => 'mysqli_query($db, ',
        '/mysql_fetch_assoc\s*\(/i' => 'mysqli_fetch_assoc(',
        '/mysql_fetch_array\s*\(/i' => 'mysqli_fetch_array(',
        '/mysql_num_rows\s*\(/i'    => 'mysqli_num_rows(',

        // Deprecated functions
        '/\bsplit\s*\(/i'           => 'explode(',
        '/\bereg\s*\(/i'            => 'preg_match(',
        '/\beregi\s*\(/i'           => 'preg_match(',

        // PHP Arrays
        '/array\s*\((.*?)\)/s'      => '[$1]',
    ];

    protected array $report = [];

    public function __construct(string $projectPath)
    {
        if (!is_dir($projectPath)) {
            throw new Exception("Project directory not found.");
        }

        $this->projectPath = realpath($projectPath);
    }

    public function run(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectPath)
        );

        foreach ($iterator as $file) {

            if (!$file->isFile()) {
                continue;
            }

            if (!in_array(strtolower($file->getExtension()), $this->extensions)) {
                continue;
            }

            $this->upgradeFile($file->getPathname());
        }

        return $this->report;
    }

    protected array $deprecatedFeatures = [

    // PHP 7+
    [
        'version' => '7.0',
        'pattern' => '/\bmysql_[a-zA-Z0-9_]+\s*\(/i',
        'message' => 'mysql_* extension removed. Convert to PDO or MySQLi.'
    ],
    [
        'version' => '7.0',
        'pattern' => '/\bereg(i|_replace)?\s*\(/i',
        'message' => 'ereg() family removed. Use preg_* functions.'
    ],
    [
        'version' => '7.0',
        'pattern' => '/\bsplit\s*\(/i',
        'message' => 'split() removed. Use explode() or preg_split().'
    ],
    [
        'version' => '7.0',
        'pattern' => '/\bcall_user_method(_array)?\s*\(/i',
        'message' => 'call_user_method() removed.'
    ],

    // PHP 7.2
    [
        'version' => '7.2',
        'pattern' => '/\beach\s*\(/i',
        'message' => 'each() deprecated then removed.'
    ],

    // PHP 7.4
    [
        'version' => '7.4',
        'pattern' => '/create_function\s*\(/i',
        'message' => 'Replace with anonymous functions.'
    ],

    // PHP 8.0
    [
        'version' => '8.0',
        'pattern' => '/\b__autoload\s*\(/i',
        'message' => 'Use spl_autoload_register().'
    ],
    [
        'version' => '8.0',
        'pattern' => '/\bget_magic_quotes_gpc\s*\(/i',
        'message' => 'Magic quotes removed.'
    ],
    [
        'version' => '8.0',
        'pattern' => '/\bget_magic_quotes_runtime\s*\(/i',
        'message' => 'Removed in PHP 8.'
    ],
    [
        'version' => '8.0',
        'pattern' => '/\bset_magic_quotes_runtime\s*\(/i',
        'message' => 'Removed in PHP 8.'
    ],
    [
        'version' => '8.0',
        'pattern' => '/\bassert\s*\(.*?,/i',
        'message' => 'String assertions no longer supported.'
    ],

    // PHP 8.1
    [
        'version' => '8.1',
        'pattern' => '/FILTER_SANITIZE_STRING/',
        'message' => 'Deprecated. Use htmlspecialchars() or FILTER_UNSAFE_RAW.'
    ],

    // PHP 8.2
    [
        'version' => '8.2',
        'pattern' => '/\$[a-zA-Z_][a-zA-Z0-9_]*->[a-zA-Z_][a-zA-Z0-9_]*\s*=/',
        'message' => 'Possible dynamic property. PHP 8.2 deprecates dynamic properties.'
    ]
];

    protected function detectDeprecatedFeatures(string $filename, string $code): void
    {
    foreach ($this->deprecatedFeatures as $feature) {

        if (preg_match($feature['pattern'], $code)) {

            $this->report[] = [
                'file' => $filename,
                'status' => 'Warning',
                'version' => $feature['version'],
                'message' => $feature['message']
            ];
        }
    }
    }

    /**
 * Analyze PHP source using the tokenizer.
 */
protected function tokenizeFile(string $filename): void
{
    $code = file_get_contents($filename);

    $tokens = token_get_all($code);

    foreach ($tokens as $i => $token) {

        if (!is_array($token)) {
            continue;
        }

        [$id, $text, $line] = $token;

        switch ($id) {

            case T_STRING:

                $function = strtolower($text);

                $removed = [

                    'mysql_connect',
                    'mysql_pconnect',
                    'mysql_query',
                    'mysql_fetch_array',
                    'mysql_fetch_assoc',
                    'mysql_num_rows',

                    'ereg',
                    'eregi',
                    'ereg_replace',
                    'eregi_replace',

                    'split',
                    'spliti',

                    'each',

                    'create_function',

                    'call_user_method',
                    'call_user_method_array',

                    'get_magic_quotes_gpc',
                    'get_magic_quotes_runtime',
                    'set_magic_quotes_runtime'

                ];

                if (in_array($function, $removed, true)) {

                    $this->report[] = [
                        'type' => 'Removed Function',
                        'file' => $filename,
                        'line' => $line,
                        'function' => $function
                    ];
                }

                break;

            case T_VAR:

                $this->report[] = [
                    'type' => 'Legacy Property',
                    'file' => $filename,
                    'line' => $line,
                    'message' => 'Replace var with public/private/protected.'
                ];

                break;

            case T_EXIT:

                $this->report[] = [
                    'type' => 'Review',
                    'file' => $filename,
                    'line' => $line,
                    'message' => 'Verify exit()/die() usage.'
                ];

                break;

        }
    }
}

    protected function upgradeFile(string $filename): void
    {
        $this->tokenizeFile($filename);
        $original = file_get_contents($filename);
        $updated = $original;
        
        foreach ($this->replacements as $find => $replace) {
            $updated = preg_replace($find, $replace, $updated);
        }

        if ($updated !== $original) {

            if ($this->backup) {
                copy($filename, $filename . '.bak');
            }

            file_put_contents($filename, $updated);

            $this->report[] = [
                'file' => $filename,
                'status' => 'Updated'
            ];
        } else {
            $this->report[] = [
                'file' => $filename,
                'status' => 'No Changes'
            ];
        }
    }

    public function enableBackups(bool $enabled = true): void
    {
        $this->backup = $enabled;
    }

    public function addReplacement(string $pattern, string $replacement): void
    {
        $this->replacements[$pattern] = $replacement;
    }

    public function getReport(): array
    {
        return $this->report;
    }
}