<?php

declare(strict_types=1);

// Project-owned functional bootstrap. Required because the phive-installed
// phpunit phar does not have access to the project's Composer autoloader,
// so the upstream bootstrap (which expects Testbase already loaded) fails.
require dirname(__DIR__) . '/vendor/autoload.php';

(static function (): void {
    $testbase = new \TYPO3\TestingFramework\Core\Testbase();
    $testbase->defineOriginalRootPath();
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/tests');
    $testbase->createDirectory(ORIGINAL_ROOT . 'typo3temp/var/transient');
})();
