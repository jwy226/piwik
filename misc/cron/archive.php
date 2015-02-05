<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Piwik\Container\StaticContainer;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

if (!defined('PIWIK_INCLUDE_PATH')) {
    define('PIWIK_INCLUDE_PATH', realpath(dirname(__FILE__) . "/../.."));
}

if (!defined('PIWIK_USER_PATH')) {
    define('PIWIK_USER_PATH', PIWIK_INCLUDE_PATH);
}

define('PIWIK_ENABLE_DISPATCH', false);
define('PIWIK_ENABLE_ERROR_HANDLER', false);
define('PIWIK_ENABLE_SESSION_START', false);
require_once PIWIK_INCLUDE_PATH . "/index.php";

if (!empty($_SERVER['argv'][0])) {
    $callee = $_SERVER['argv'][0];
} else {
    $callee = '';
}

if (false !== strpos($callee, 'archive.php')) {
    $piwikHome = PIWIK_INCLUDE_PATH;
    echo "
-------------------------------------------------------
Using this 'archive.php' script is no longer recommended.
Please use '/path/to/php $piwikHome/console core:archive " . implode('', array_slice($_SERVER['argv'], 1)) . "' instead.
To get help use '/path/to/php $piwikHome/console core:archive --help'
See also: http://piwik.org/docs/setup-auto-archiving/

If you cannot use the console because it requires CLI
try 'php archive.php --url=http://your.piwik/path'
-------------------------------------------------------
\n\n";
}

if (isset($_SERVER['argv']) && Piwik\Console::isSupported()) {
    $console = new Piwik\Console();

    // manipulate command line arguments so CoreArchiver command will be executed
    $script = array_shift($_SERVER['argv']);
    array_unshift($_SERVER['argv'], 'core:archive');
    array_unshift($_SERVER['argv'], $script);

    $console->run();
} else { // if running via web request, use CronArchive directly

    if (Piwik\Common::isPhpCliMode()) {
        // We can run the archive in CLI with `php-cgi` so we have to configure the container/logger
        // just like for CLI
        StaticContainer::setEnvironment('cli');
        /** @var ConsoleHandler $consoleLogHandler */
        $consoleLogHandler = StaticContainer::get('Symfony\Bridge\Monolog\Handler\ConsoleHandler');
        $consoleLogHandler->setOutput(new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE));
    } else {
        // HTTP request: logs needs to be dumped in the HTTP response (on top of existing log destinations)
        /** @var \Monolog\Logger $logger */
        $logger = StaticContainer::get('Psr\Log\LoggerInterface');
        $handler = new StreamHandler('php://output', Logger::INFO);
        $handler->setFormatter(StaticContainer::get('Piwik\Log\Formatter\LineMessageFormatter'));
        $logger->pushHandler($handler);
    }

    $archiver = new Piwik\CronArchive();

    if (!Piwik\Common::isPhpCliMode()) {
        $token_auth = Piwik\Common::getRequestVar('token_auth', '', 'string');

        if (!$archiver->isTokenAuthSuperUserToken($token_auth)) {
            die('<b>You must specify the Super User token_auth as a parameter to this script, eg. <code>?token_auth=XYZ</code> if you wish to run this script through the browser. </b><br>
                    However it is recommended to run it <a href="http://piwik.org/docs/setup-auto-archiving/">via cron in the command line</a>, since it can take a long time to run.<br/>
                    In a shell, execute for example the following to trigger archiving on the local Piwik server:<br/>
                    <code>$ /path/to/php /path/to/piwik/console core:archive --url=http://your-website.org/path/to/piwik/</code>');
        }
    }

    $archiver->main();
}