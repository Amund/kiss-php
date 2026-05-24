#!/usr/bin/env php
<?php

$rootDir = __DIR__;
$vendorDir = $rootDir . '/vendor';
$pharFile = $rootDir . '/kiss.phar';

if (!is_dir($vendorDir)) {
    fwrite(STDERR, "Run 'composer install' first.\n");
    exit(1);
}

if (is_file($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile, 0, 'kiss.phar');
$phar->startBuffering();

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$filtered = new CallbackFilterIterator($iterator, function ($fileinfo) use ($rootDir) {
    $rel = substr($fileinfo->getPathname(), strlen($rootDir) + 1);
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    $top = $parts[0] ?? '';

    if (!in_array($top, ['src', 'vendor'], true) && $top !== 'composer.json') {
        return false;
    }

    foreach ($parts as $part) {
        if (in_array($part, ['.git', 'test', 'tests', 'docs'], true)) {
            return false;
        }
    }

    return $fileinfo->isFile();
});

$phar->buildFromIterator($filtered, $rootDir);

$stub = "#!/usr/bin/env php\n<?php Phar::mapPhar('kiss.phar'); require 'phar://kiss.phar/src/bin/kiss'; __HALT_COMPILER(); ?>\n";
$phar->setStub($stub);

$phar->stopBuffering();
chmod($pharFile, 0755);

$size = round(filesize($pharFile) / 1024);
echo "Created: $pharFile ($size KB)\n";
