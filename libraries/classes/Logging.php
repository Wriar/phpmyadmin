<?php
/**
 * Logging functionality for webserver.
 *
 * This includes web server specific code to log some information.
 */

declare(strict_types=1);

namespace PhpMyAdmin;

use function closelog;
use function date;
use function error_log;
use function function_exists;
use function openlog;
use function syslog;

use const LOG_AUTHPRIV;
use const LOG_NDELAY;
use const LOG_PID;
use const LOG_WARNING;

/**
 * Misc logging functions
 */
class Logging
{
    /**
     * Get authentication logging destination
     */
    public static function getLogDestination(): string
    {
        $logFile = $GLOBALS['config']->get('AuthLog');

        /* Autodetect */
        if ($logFile === 'auto') {
            if (function_exists('syslog')) {
                $logFile = 'syslog';
            } elseif (function_exists('error_log')) {
                $logFile = 'php';
            } else {
                $logFile = '';
            }
        }

        return $logFile;
    }

    /**
     * Generate log message for authentication logging
     *
     * @param string $user   user name
     * @param string $status status message
     */
    public static function getLogMessage(string $user, string $status): string
    {
        if ($status === 'ok') {
            return 'user authenticated: ' . $user . ' from ' . Core::getIp();
        }

        return 'user denied: ' . $user . ' (' . $status . ') from ' . Core::getIp();
    }

    /**
     * Logs user information to webserver logs.
     *
     * @param string $user   user name
     * @param string $status status message
     */
    public static function logUser(string $user, string $status = 'ok'): void
    {
        if (function_exists('apache_note')) {
            apache_note('userID', $user);
            apache_note('userStatus', $status);
        }

        /* Do not log successful authentications */
        if (! $GLOBALS['config']->get('AuthLogSuccess') && $status === 'ok') {
            return;
        }

        $logFile = self::getLogDestination();
        if (empty($logFile)) {
            return;
        }

        $message = self::getLogMessage($user, $status);
        if ($logFile === 'syslog') {
            if (function_exists('syslog')) {
                @openlog('phpMyAdmin', LOG_NDELAY | LOG_PID, LOG_AUTHPRIV);
                @syslog(LOG_WARNING, $message);
                closelog();
            }
        } elseif ($logFile === 'php') {
            @error_log($message);
        } elseif ($logFile === 'sapi') {
            @error_log($message, 4);
        } else {
            @error_log(
                date('M d H:i:s') . ' phpmyadmin: ' . $message . "\n",
                3,
                $logFile,
            );
        }
    }
}
