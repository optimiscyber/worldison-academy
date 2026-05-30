<?php
// Minimal .env loader for this project
function load_dotenv($path)
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($name, $val) = explode('=', $line, 2);
        $name = trim($name);
        $val = trim($val);
        if (!getenv($name)) putenv("$name=$val");
        if (!isset($_ENV[$name])) $_ENV[$name] = $val;
        if (!isset($_SERVER[$name])) $_SERVER[$name] = $val;
    }
}

// load .env at project root
$root = __DIR__ . '/../.env';
load_dotenv($root);

?>
