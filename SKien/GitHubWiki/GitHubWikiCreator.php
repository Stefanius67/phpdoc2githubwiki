<?php
declare(strict_types=1);

namespace SKien\GitHubWiki;

use SKien\Config\ConfigInterface;
use SKien\Config\NullConfig;
use SKien\Config\XMLConfig;
use lordgnu\CLICommander\CLICommander;

/**
 * Creator to build a github wiki class reference based on phpDoc comments
 * using phpDocumentor 3.
 *
 * <ul>
 * <li> Get all config values (cmdlineargs - config file - default value) </li>
 * <li> Check the configuration and the environment </li></ul>
 *
 * @package GitHubWiki
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GitHubWikiCreator
{
    protected const VERSION = '1.0.0';
    protected const TITLE = 'Class reference';
    protected const PHPDOC_CMD = 'phpDocumentor.phar';
    protected const CONFIG_FILE = 'githubwiki.xml';
    protected const CACHE_PATH = './cache';
    protected const PHPDOC_CONFIG_TEMPLATE = 'phpdoc.template.xml';
    protected const PHPDOC_AUTOCONFIG = 'phpdoc-auto.xml';
    protected const MSG_ALLWAYS = 0;
    protected const MSG_VERBOSE = 1;
    protected const MSG_DEBUG = 2;

    /** @var CLICommander used to parse cmdline arguments and for console output     */
    protected CLICommander $oCli;
    /** @var ConfigInterface the creator configuration     */
    protected ConfigInterface $oConfig;
    /** @var string config file to use */
    protected string $strConfigFile;
    /** @var string title of the wiki     */
    protected string $strTitle;
    /** @var string the command to call the phpDocumentor     */
    protected string $strPhpDocCmd;
    /** @var string phpDocumentor config file to use */
    protected string $strPhpDocConfigFile = '';
    /** @var string phpDocumentor template to use */
    protected string $strPhpDocTemplate = '';
    /** @var string the directory where the wiki repository to find     */
    protected string $strWikiPath;
    /** @var string the directory for cache and temp files     */
    protected string $strCachePath;
    /** @var string project directory containing the source     */
    protected string $strProjectPath;
    /** @var string errors while validation    */
    protected string $strError = '';
    /** @var bool generate more detailed output    */
    protected bool $bVerbose = false;
    /** @var bool generate debug output    */
    protected bool $bDebug = false;
    /** @var bool silent operation    */
    protected bool $bSilent = false;
    /** @var bool suppress git operations    */
    protected bool $bSuppressRun = false;

    /**
     * Create instance and initialize CLI-commander property.
     * @param CLICommander $oCli
     */
    public function __construct(CLICommander $oCli)
    {
        $this->oCli = $oCli;
    }

    /**
     * Only display current version.
     */
    public function displayVersion() : void
    {
        $this->writeConsole("GitHubWiki creator v" . self::VERSION);
    }

    /**
     * Initialize the creator.
     * Get all config values with following priority: <ol>
     * <li> command line arguments </li>
     * <li> specified config file </li>
     * <li> config githubwiki.xml in current working directory, if no config specified or
     *      the specified config did not exist </li>
     * <li> default values (if defined) </li></ol>
     */
    public function init() : void
    {
        $this->writeConsole("{cyan||bold}GitHubWiki creator v1.0.0 by Stefan Kientzler{reset}\n");
        $this->getConfig();
        $this->readConfig();
        $this->readCmdlineArgs();

        // All path must be absolute for phpDocumentor
        $this->strWikiPath = $this->makeAbsolutePath($this->strWikiPath);
        $this->strCachePath = $this->makeAbsolutePath($this->strCachePath);
        $this->strProjectPath = $this->makeAbsolutePath($this->strProjectPath);

        $this->writeConsole("> creating GitHub wiki {cyan||bold}[" . $this->strTitle . "] in [" . $this->strWikiPath. "]{reset}", self::MSG_VERBOSE);
        if (!empty($this->strConfigFile)) {
            $this->writeConsole("> using config file {cyan||bold}[" . $this->strConfigFile . "]{reset}", self::MSG_VERBOSE);
        }
    }

    /**
     * Create absolute path.
     * Check whether the path is relative and change it to the absolute path based on
     * the current working directory.
     * On UX systems absolute path always begin with the DIRECTORY_SEPARATOR. On Windows
     * absolute path may also begin with a drive letter and a ':'.
     * @param string $strPath
     * @return string
     */
    protected function makeAbsolutePath(string $strPath) : string
    {
        $strPath = rtrim($strPath, DIRECTORY_SEPARATOR);
        if (substr($strPath, 0, 1) == DIRECTORY_SEPARATOR || substr($strPath, 1, 1) == ':') {
            return $strPath;
        }
        $strPath = getcwd() . DIRECTORY_SEPARATOR . $strPath;
        return realpath($strPath);
    }

    /**
     * Check the configuration and the environment.
     * To run the creator: <ul>
     * <li> the output path for the wiki must <ul>
     *     <li> be specified </li>
     *     <li> exist </li>
     *     <li> is a directory (not a file!) </li>
     *     <li> contain a git repository </ul></li>
     * <li> git must be installed </li>
     * <li> phpDocumentor <ul>
     *     <li>must be installed </li>
     *     <li>must be at least version 3 </li></ul></li>
     * </ul>
     * @return bool
     */
    public function isValid() : bool
    {
        $strError = $this->validateGit();
        $strError .= "\n" . $this->validatePhpDocumentor();
        $strError = trim($strError);
        $strError .= "\n" . $this->validateWikiPath();
        $strError = trim($strError);
        if (empty($strError)) {
            if (empty($this->strPhpDocTemplate)) {
                $strError .= "\n" . $this->copyTemplate();
                $strError = trim($strError);
            }
            if (empty($this->strPhpDocConfigFile)) {
                $strError .= "\n" . $this->createPhpDocConfig();
                $strError = trim($strError);
            }
        }
        if (!empty($strError)) {
            $this->writeConsole("{white|red}\n" . $strError . "{reset}");
        }
        return empty($strError);
    }

    /**
     * Build the github wiki.
     * Steps to perform: <ul>
     * <li> sync local and remote repo <ul>
     *     <li> commit local changes made manually (and added images) </li>
     *     <li> pull current version from remote </li></ul></li>
     * <li> generate the wiki using phpDocumentor </li>
     * <li> commit and push generated wiki </li>
     * </ul>
     */
    public function build() : void
    {
        if (!$this->pullRepo()) {
            return;
        }
        if (!$this->generateWiki()) {
            return;
        }
        $this->pushRepo();
    }

    /**
     * Get the assigned config.
     * If config is specified as cmdline arg, this config is searched for. If
     * no config specified or file does not exist, we look for a default config
     * in the current workin directory.
     * TODO: first look for a global config in the 'home' directory, and read
     * this before reading local one (merge)
     * If no config can be found, a NullConfig is created.
     */
    protected function getConfig() : void
    {
        // config specified as cmdline arg?
        $this->strConfigFile = self::CONFIG_FILE;
        if ($this->oCli->ArgumentPassed('config')) {
            $this->strConfigFile = $this->oCli->GetArgumentValue('config');
            if (!file_exists($this->strConfigFile)) {
                // fall back to default...
                $this->strConfigFile = self::CONFIG_FILE;
            }
        }
        if (file_exists($this->strConfigFile)) {
            $this->oConfig = new XMLConfig($this->strConfigFile);
        } else {
            $this->strConfigFile = '';
            $this->oConfig = new NullConfig();
        }
    }

    /**
     * Read the given config.
     * Not existing values are initialized with default values, if defined.
     */
    protected function readConfig() : void
    {
        // read from config or initialize with default
        $this->strTitle = $this->oConfig->getString('title', self::TITLE);
        $this->strPhpDocCmd = $this->oConfig->getString('phpdoc.command', self::PHPDOC_CMD);
        $this->strPhpDocConfigFile = $this->oConfig->getString('phpdoc.config');
        $this->strPhpDocTemplate = $this->oConfig->getString('phpdoc.template');
        $this->strWikiPath = $this->oConfig->getString('paths.output');
        $this->strCachePath = $this->oConfig->getString('paths.cache', self::CACHE_PATH);
        $this->strProjectPath = $this->oConfig->getString('paths.project');
        $this->bVerbose = $this->oConfig->getBool('options.verbose');
        $this->bDebug = $this->oConfig->getBool('options.debug');
        $this->bSuppressRun = $this->oConfig->getBool('options.norun');
    }

    /**
     * Evaluation of the command line.
     * After the configuration files have been read out, the last thing to be evaluated
     * is the command line, with which all previous values can be overwritten.
     */
    protected function readCmdlineArgs() : void
    {
        // may overwrite with cmdline arg
        $this->strTitle = $this->getArgumentValue('title', $this->strTitle);
        $this->strPhpDocCmd = $this->getArgumentValue('phpdoc', $this->strPhpDocCmd);
        $this->strWikiPath = $this->getArgumentValue('wiki', $this->strWikiPath);
        $this->bVerbose = $this->oCli->ArgumentPassed('verbose') || $this->oCli->ArgumentPassed('v') || $this->bVerbose;
        $this->bDebug = $this->oCli->ArgumentPassed('debug') || $this->bDebug;
        $this->bSilent = $this->oCli->ArgumentPassed('silent');
    }

    /**
     * Get the value of an argument.
     * If the argument isn't specified in the cmdline, the methiod returns the
     * default value.
     * @param string $strName     Name of the argument
     * @param string $strDefault  Default value to return, if argument isn't specified
     * @return string
     */
    protected function getArgumentValue(string $strName, string $strDefault = '') : string
    {
        $strValue = $strDefault;
        if ($this->oCli->ArgumentPassed($strName)) {
            $strValue = $this->oCli->GetArgumentValue($strName);
        }
        return $strValue;
    }

    /**
     * Validate the wiki path.
     * @return string
     */
    protected function validateWikiPath() : string
    {
        $strError = '';
        if (empty($this->strWikiPath)) {
            $strError = "- No wiki path specified!";
        } elseif (!file_exists($this->strWikiPath)) {
            $strError = "- The wiki path [" . $this->strWikiPath . "] does not exist!";
        } elseif (!is_dir($this->strWikiPath)) {
            $strError = "- [" . $this->strWikiPath . "] must be a directory!";
        } elseif (!file_exists($this->strWikiPath . DIRECTORY_SEPARATOR . '.git')) {
            $strError = "- The path [" . $this->strWikiPath . "] doesn't contain a git repository!";
        }
        return $strError;
    }

    /**
     * Validate installed git command line tool.
     * @return string
     */
    protected function validateGit() : string
    {
        $strError = '';
        if (shell_exec('git --version 2>nul') === null) {
            $strError = "- git not found!";
        }
        return $strError;
    }

    /**
     * Validate phpDocumentor.
     * - check if can be called
     * - check for version >= 3
     * - check for cache path and try to create, if not exist
     * - check for existence of external phpdoc confifuration, if specified
     * - check for existence of template, if specified
     * @return string
     */
    protected function validatePhpDocumentor() : string
    {
        $strError = '';
        $strResult = shell_exec($this->strPhpDocCmd . ' --version 2>nul');
        if ($strResult === null) {
            $strError = "- phpDocumentor not found! Command: " . $this->strPhpDocCmd;
        } elseif (substr($strResult, 0, 16) != 'phpDocumentor v3') {
            $strError = "- at least version 3.0 of phpDocumentor is needed! found " . $strResult;
        }

        // try to create cache directory if not exist
        if (!file_exists($this->strCachePath)) {
            if (!@mkdir($this->strCachePath, 0777, true)) {
                $strError .= "\n- Cannot create the cache path [" . $this->strCachePath . "]!";
            }
        }
        if (!is_dir($this->strCachePath)) {
            $strError .= "\n- [" . $this->strCachePath . "] must be a directory!";
        }

        // check for existence of external phpdoc confifuration, if specified
        if (!empty($this->strPhpDocConfigFile) && !file_exists($this->strPhpDocConfigFile)) {
            $strError .= "\n- phpDocumentor configuration [" . $this->strPhpDocConfigFile ."] not found!";
        }

        // check for existence of template, if specified
        if (!empty($this->strPhpDocTemplate) && !file_exists($this->strPhpDocTemplate)) {
            $strError .= "\n- phpDocumentor template [" . $this->strPhpDocTemplate ."] not found!";
        }
        return trim($strError);
    }

    /**
     * Create the phpDocumentor config file from our configuration.
     * If no external phpDocumentor config file is specified, we create one
     * using all information from our config in the cache directory.
     * @return string
     */
    protected function createPhpDocConfig() : string
    {
        $strTemplate = self::PHPDOC_CONFIG_TEMPLATE;
        if (!empty(\Phar::running())) {
            $strTemplate = \Phar::running() . '/' . $strTemplate;
        }
        $strXML = @file_get_contents($strTemplate);
        if ($strXML === false) {
            return "- Can't open the template file [" . self::PHPDOC_CONFIG_TEMPLATE . "]";
        }
        // replace some placeholders ...
        $strXML = str_replace('{title}', $this->strTitle, $strXML);
        $strXML = str_replace('{path.output}', $this->strWikiPath, $strXML);
        $strXML = str_replace('{path.cache}', $this->strCachePath, $strXML);
        $strXML = str_replace('{path.project}', $this->strProjectPath, $strXML);
        $strXML = str_replace('{template}', $this->strPhpDocTemplate, $strXML);

        // ... create DOM document ...
        $oDOMDoc = new \DOMDocument();
        $oDOMDoc->preserveWhiteSpace = false;
        $oDOMDoc->formatOutput = true;
        if ($oDOMDoc->loadXML($strXML) === false) {
            return "- Can't create DOM document!";
        }

        // ... and insert some dynamic nodes from configuration
        $this->addChildNodes($oDOMDoc, 'source', 'source.path', 'path');
        $this->addChildNodes($oDOMDoc, 'ignore-tags', 'ignore-tags.ignore-tag', 'ignore-tag');
        $this->addChildNodes($oDOMDoc, 'api', 'visibilities.visibility', 'visibility');

        $this->strPhpDocConfigFile = $this->strCachePath . '/' . self::PHPDOC_AUTOCONFIG;
        if (!@$oDOMDoc->save($this->strPhpDocConfigFile)) {
            return "- Can't write phpDocumentor config [" . $this->strPhpDocConfigFile . "]!";
        }
        return '';
    }

    /**
     * Copy the template to the cahe path.
     * @return string
     */
    protected function copyTemplate() : string
    {
        if (!empty(\Phar::running())) {
            $strSrcPath = \Phar::running() . '/template';
        } else {
            $strSrcPath = realpath(__DIR__ . '/../../template');
        }
        $strDstPath = $this->strCachePath . '/template';
        if (!file_exists($strSrcPath)) {
            return "- The template path [" . $strSrcPath . "] does not exist!";
        } elseif (!is_dir($strSrcPath)) {
            return "- [" . $strSrcPath . "] must be a directory!";
        } elseif (count(scandir($strSrcPath)) == 2) {
            // count of 2 indicates only '.' and '..'
            return "- The template path [" . $strSrcPath . "] is empty!";
        }

        if (!file_exists($strDstPath)) {
            if (!@mkdir($strDstPath, 0777, true)) {
                return "- Cannot create the template path [" . $strDstPath . "]!";
            }
        }
        $this->xcopy($strSrcPath, $strDstPath);
        $this->strPhpDocTemplate = $strDstPath;

        return '';
    }

    /**
     * Recursive copy of a directory.
     * @param string $strSrcPath
     * @param string $strDstPath
     */
    protected function xcopy(string $strSrcPath, string $strDstPath) : void
    {
        $dir = opendir($strSrcPath);
        @mkdir($strDstPath);
        while( $strFile = readdir($dir) ) {
            if (($strFile != '.') && ($strFile != '..')) {
                $strSrcFile = $strSrcPath . '/' . $strFile;
                $strDstFile = $strDstPath . '/' . $strFile;
                if (is_dir($strSrcFile) ) {
                    // recursive call for subfolders
                    $this->xcopy($strSrcFile, $strDstFile);
                } else {
                    copy($strSrcFile, $strDstFile);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Add childnodes to the parent read from config.
     * @param \DOMDocument $oDOMDoc
     * @param string $strParent name of the parent node
     * @param string $strConfig key to read from config
     * @param string $strChild  name of the child nodes
     */
    private function addChildNodes(\DOMDocument $oDOMDoc, string $strParent, string $strConfig, string $strChild) : void
    {
        $oList = $oDOMDoc->getElementsByTagName($strParent);
        if ($oList->length == 1) {
            $oParent = $oList->item(0);
            $aValues = $this->oConfig->getArray($strConfig);
            foreach ($aValues as $strValue) {
                $oChild = $oDOMDoc->createElement($strChild, $strValue);
                $oParent->appendChild($oChild);
            }
        }
    }

    /**
     * Pull remote repository.
     * @return bool
     */
    protected function pullRepo() : bool
    {
        $strCWD = getcwd();
        if ($strCWD === false) {
            $this->writeConsole("{white|red}- Error getting current working directory!{reset}");
            return false;
        }
        chdir($this->strWikiPath);

        // first commit local changes made manualy to avoid conflicts ...
        $this->writeConsole("> commit local changes made manualy");
        $this->execGitCommand('add --all');
        $this->execGitCommand('commit -a -m "manual changes ' . date('Y-m-d') . '"');

        // the add command always return 0, and since git returns errorcode 1 in case of
        // 'nothing to commit, working tree clean', we just go on independent of the result code...

        // .. and pull current version
        $this->writeConsole("> pull current version from remote");
        $iResult = $this->execGitCommand('pull origin master');

        chdir($strCWD);
        return $iResult == 0;
    }

    /**
     * Generate the wiki using phpDocumentor.
     * @return bool
     */
    protected function generateWiki() : bool
    {
        $this->writeConsole("> generate the wiki");
        $strCommand = $this->strPhpDocCmd . ' run -c ' . $this->strPhpDocConfigFile . ' 2>&1';
        if ($this->bSuppressRun) {
            $this->writeConsole("{yellow}  phpDocumentor > suppressed run (" . $strCommand . ")!{reset}", self::MSG_VERBOSE);
            return true;
        }
        $aOutput = array();
        $iResult = 0;
        exec($strCommand, $aOutput, $iResult);
        $strResponse = '';
        foreach ($aOutput as $strLine) {
            $strResponse .= "  phpDocumentor > " . $strLine . "\n";
        }
        $strResponse = rtrim($strResponse);
        if (!empty($strResponse)) {
            $this->writeConsole("{yellow}" . $strResponse . "{reset}", self::MSG_VERBOSE);
        }
        $strColor = ($iResult == 0) ? '{green}' : '{red}';
        $this->writeConsole($strColor ."  phpDocumentor > result: " . $iResult . "{reset}", self::MSG_DEBUG);
        return ($iResult == 0);
    }

    /**
     * Commit and push the generated wiki to remote repo.
     * @return bool
     */
    protected function pushRepo() : bool
    {
        $strCWD = getcwd();
        if ($strCWD === false) {
            $this->writeConsole("{white|red}- Error getting current working directory!{reset}");
            return false;
        }
        chdir($this->strWikiPath);

        // commit and push generated wiki
        $this->writeConsole("> commit generated wiki");
        $this->execGitCommand('add --all');
        $this->execGitCommand('commit -a -m "phpDoc builded wiki ' . date('Y-m-d') . '"');
        $this->writeConsole("> push generated wiki");
        $iResult = $this->execGitCommand('push origin master');

        chdir($strCWD);
        return $iResult == 0;
    }

    /**
     * Execute the requested git command.
     * - The git output is written to console in VERBOSE mode.
     * - The result of the git call is written to console in DEBUG mode.
     * @param string $strCommand    git command to execute
     * @return int return code of the call
     */
    protected function execGitCommand(string $strCommand) : int
    {
        if ($this->bSuppressRun) {
            $this->writeConsole("{yellow}  git > suppressed command: " . $strCommand . "{reset}", self::MSG_VERBOSE);
            return 0;
        }
        $aOutput = array();
        $iResult = 0;
        exec('git ' . $strCommand . ' 2>&1', $aOutput, $iResult);
        $strResponse = '';
        foreach ($aOutput as $strLine) {
            $strResponse .= "  git > " . $strLine . "\n";
        }
        $strResponse = rtrim($strResponse);
        if (!empty($strResponse)) {
            $this->writeConsole("{yellow}" . $strResponse . "{reset}", self::MSG_VERBOSE);
        }
        $strColor = ($iResult == 0) ? '{green}' : '{red}';
        $this->writeConsole($strColor ."  git > result: " . $iResult . " (command: " . $strCommand . "){reset}", self::MSG_DEBUG);
        return $iResult;
    }

    /**
     * Write formated message to the console.
     * @param string $strText   text to write
     * @param int $iLevel       display level of the message
     */
    protected function writeConsole(string $strText, int $iLevel = self::MSG_ALLWAYS) : void
    {
        if ($this->bSilent) {
            return;
        }
        $bShow = $iLevel == self::MSG_ALLWAYS;
        $bShow = $bShow || ($iLevel == self::MSG_VERBOSE && $this->bVerbose);
        $bShow = $bShow || ($iLevel == self::MSG_DEBUG && $this->bDebug);
        if ($bShow) {
            $this->oCli->WriteTemplate($strText);
        }
    }
}