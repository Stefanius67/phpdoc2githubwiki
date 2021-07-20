<?php
spl_autoload_register(function($strClass) {
    $strInclude = '';
    if (strpos($strClass, '\\') > 1) {
        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $strInclude = str_replace('\\', DIRECTORY_SEPARATOR, $strClass) . '.php';
    }

    // if the file exists, require it
    if (strlen($strInclude) > 0) {
        if (empty(\Phar::running())) {
            $strInclude = dirname(__FILE__) . DIRECTORY_SEPARATOR . $strInclude;
        } else {
            $strInclude = \Phar::running() . DIRECTORY_SEPARATOR . $strInclude;
        }

        if (file_exists($strInclude)) {
            require $strInclude;
        }
    }
});

