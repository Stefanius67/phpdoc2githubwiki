<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

use SKien\GitHubWiki\GitHubWikiCreator;
use lordgnu\CLICommander\CLICommander;

$oCli = new CLICommander();

// print_r($oCli->GetArguments());

$oCreator = new GitHubWikiCreator($oCli);

exit($oCreator->run() ? 0 : 1);