<?php
$filename = 'gut.phar';
$output_dir = __DIR__ . '/build/';
$output_file = $output_dir . $filename;
// Remove existing file, recursively create directories if needed
@unlink($output_file);
@mkdir($output_dir, 0755, true);
// If vendors directory doesn't exist, try to install with composer
if (! is_dir(__DIR__ . '/vendor')) {
    @shell_exec('composer --working-dir="' . __DIR__ . '" install --no-dev');
}
// Start phar
$phar = new Phar(
    $output_file,
    FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_FILENAME,
    $filename
);
$phar->startBuffering();
// Adding folders
$phar->buildFromDirectory(
    dirname(__FILE__),
    '/(src|vendor)\/.*$/'
);
// Adding main file
$phar->addFile('gut.php');
// Create a stub and add the shebang
$stub = $phar->createDefaultStub('gut.php');
$stub = "#!/usr/bin/env php \n<?php error_reporting(0) ?>\n" . $stub;

$phar->setStub($stub);
$phar->compressFiles(Phar::GZ);
$phar->stopBuffering();
// Set file permissions
chmod($output_file, 0755);
rename($output_file, str_replace('.phar', '', $output_file));
