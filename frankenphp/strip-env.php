<?php
$path = __DIR__ . '/../.env.local.php';
$env = require $path;
unset($env['APP_BASE_URL']);
file_put_contents($path, '<?php return ' . var_export($env, true) . ';' . PHP_EOL);
