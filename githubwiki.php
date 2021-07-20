<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

use SKien\GitHubWiki\GitHubWikiCreator;
use lordgnu\CLICommander\CLICommander;

if (php_sapi_name() !== 'cli') {
    echo '!! this script is designed for PHP CLI !!';
    exit;
}

$oCli = new CLICommander();
$oCreator = new GitHubWikiCreator($oCli);

if ($oCli->ArgumentPassed('version') || $oCli->ArgumentPassed('V')) {
    $oCreator->displayVersion();
    exit;
}

$oCreator->init();
if ($oCreator->isValid()) {
    $oCreator->build();
}
