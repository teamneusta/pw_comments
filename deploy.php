<?php
namespace Deployer;

/* | To run this task install deployer & PHP Watcher first globally:
 * |
 * |   $ composer global require deployer/deployer
 * |   $ composer global require jasonlewis/resource-watcher
 * |
 * | Then you can call `dep upload` (if vendor/bin is added to PATH env variable)
 */

set('exclude_from_upload', [
    '.git',
    '.idea',
    '.vagrant',
    'package.box',
    'vendor',
    'Vagrantfile'
]);

set('bin/rm', 'rm -Rf ');
set('bin/touch', 'touch ');
set('bin/composer:install', 'composer install --no-ansi -n');
set('bin/composer:update', 'composer update --no-ansi -n');
set('bin/webbrowser', 'explorer ');

server('vagrant', '192.168.0.100')
    ->user('vagrant')
    ->password('vagrant')
    ->set('deploy_path', '/var/www/html/typo3conf/ext/pw_comments');

// Tasks

desc('If running it watches files (respecting "exclude_from_upload" config) and uploads or deletes them on remote.');
task('watch:upload', function(){
    // /!\ Info
    // /!\ Requires installed composer package: "jasonlewis/resource-watcher" before run deployer (`dep watch`)
    $files = new \Illuminate\Filesystem\Filesystem;
    $tracker = new \JasonLewis\ResourceWatcher\Tracker;

    $watcher = new \JasonLewis\ResourceWatcher\Watcher($tracker, $files);
    $listener = $watcher->watch(getcwd());

    $listener->modify(function($resource, $path) {
        $relativePath = getRelativePath($path);
        if (isValidFile($path)) {
            if (input()->getOption('verbose')) {
                writeln('File modified <info>' . $path . '</info>');
            }
            forEachDeployPath(function($deployPath) use ($path, $relativePath) {
                if (file_exists($path)) {
                    upload($path, $deployPath . '/' . $relativePath);
                }
            });
        }
    });
    $listener->create(function($resource, $path) {
        $relativePath = getRelativePath($path);
        if (isValidFile($path)) {
            if (input()->getOption('verbose')) {
                writeln('New file <info>' . $path . '</info>');
            }
            forEachDeployPath(function($deployPath) use ($path, $relativePath) {
                if (file_exists($path)) {
                    upload($path, $deployPath . '/' . $relativePath);
                }
            });
        }
    });
    $listener->delete(function($resource, $path) {
        $relativePath = getRelativePath($path);
        if (isValidFile($path)) {
            forEachDeployPath(function($deployPath) use ($path, $relativePath) {
                writeln('Delete file <info>' . $deployPath . '/' . $relativePath . '</info>');
                run(get('bin/rm') . $deployPath . '/' . $relativePath);
            });
        }
    });
    $watcher->start();
});

desc('Runs file watcher');
task('watch', [
    'watch:upload'
]);

desc('Full uploads of local project files (without excluded files).');
task('upload', function (){
    $directory = new \RecursiveDirectoryIterator(getcwd(), \FilesystemIterator::FOLLOW_SYMLINKS | \FilesystemIterator::SKIP_DOTS);
    $files = new \RecursiveIteratorIterator($directory);
    /** @var \SplFileInfo $file */
    foreach ($files as $file) {
        $path = $file->getPathname();
        $relativePath = substr($path, strlen(getcwd()) + 1);
        if (isValidFile($file->getPathname())) {
            forEachDeployPath(function($deployPath) use ($path, $relativePath) {
                upload($path, $deployPath . '/' . $relativePath);
            });
        }
    }
});

desc('Clears configured deploy paths.');
task('clear', function() {
    forEachDeployPath(function($deployPath) {
        writeln('Clearing <info>' . $deployPath . '</info>');
        run(get('bin/rm') . $deployPath);
    });
});

desc('Initial setup. Clears all configured deploy paths first before uploading all project files.');
task('set-up', [
    'clear',
    'upload'
]);

desc('Open browser with configured server hostname/ip');
task('show', function(){
    /** @var \Deployer\Server\Remote\PhpSecLib $server */
    $server = Deployer::get()->servers->get('vagrant');
    runLocally(get('bin/webbrowser') . ' http://' . $server->getConfiguration()->getHost());
});

// Functions

/**
 * Calls given callable for each given option 'deploy_paths' (array).
 * Option 'deploy_path' is also supported but expects a string.
 *
 * @param callable $callback
 * @return void
 */
function forEachDeployPath(callable $callback)
{
    $deployPaths = [];
    if (has('deploy_paths')) {
        $deployPaths = get('deploy_paths');
        if (is_string($deployPaths)) {
            $deployPaths = [$deployPaths];
        }
    }
    if (has('deploy_path')) {
        $deployPaths = array_merge([get('deploy_path')], $deployPaths);
    }
    foreach ($deployPaths as $deployPath) {
        $callback($deployPath);
    }
}

/**
 * Checks if given file path is not excluded by "exclude_from_upload"
 * configuration parameter
 *
 * @param string $path File path
 * @return bool If true the file is not excluded and can proceed. On false the file is excluded.
 */
function isValidFile($path)
{
    $relativePath = getRelativePath($path);
    $relativePathParts = explode('/', $relativePath);

    if (in_array(basename($path), get('exclude_from_upload')) ||
        in_array(reset($relativePathParts), get('exclude_from_upload'))
    ) {
        if (input()->getOption('verbose')) {
            writeln('Ignoring <info>' . $relativePath . '</info>');
        }
        return false;
    }
    return true;
}

/**
 * Converts given path to relative path and streamline directory separators to /
 *
 * @param string $absolutePath
 * @return string Relative path to getcwd()
 */
function getRelativePath($absolutePath)
{
    return str_replace('\\', '/', substr($absolutePath, strlen(getcwd()) + 1));
}
