<?php

// php deploy/env-set.php KEY VALUE — записать значение в .env (с кавычками, если нужно)
[$_, $key, $value] = $argv + [null, null, ''];
if (! $key || ! preg_match('/^[A-Z0-9_]+$/', $key)) {
    fwrite(STDERR, "usage: php deploy/env-set.php KEY VALUE\n");
    exit(1);
}
$file = __DIR__.'/../.env';
$content = file_exists($file) ? file_get_contents($file) : '';
if ($value !== '' && preg_match('/[\s#"\'\\\\$=]/', $value)) {
    $value = '"'.addcslashes($value, '"\\$').'"';
}
$line = $key.'='.$value;
$pattern = '/^'.preg_quote($key, '/').'=.*$/m';
$content = preg_match($pattern, $content)
    ? preg_replace_callback($pattern, fn () => $line, $content)
    : rtrim($content, "\n")."\n".$line."\n";
file_put_contents($file, $content);
