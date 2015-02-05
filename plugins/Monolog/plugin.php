<?php

use Interop\Container\ContainerInterface;
use Monolog\Logger;
use Piwik\Log;

return array(

    'Psr\Log\LoggerInterface' => DI\object('Monolog\Logger')
        ->constructor('piwik', DI\link('log.handlers'), DI\link('log.processors')),

    'log.handlers' => DI\factory(function (ContainerInterface $c) {
        if ($c->has('ini.log.log_writers')) {
            $writerNames = $c->get('ini.log.log_writers');
        } else {
            return array();
        }
        $classes = array(
            'file'     => 'Piwik\Log\Handler\FileHandler',
            'screen'   => 'Piwik\Log\Handler\WebNotificationHandler',
            'database' => 'Piwik\Log\Handler\DatabaseHandler',
        );
        $writerNames = array_map('trim', $writerNames);
        $writers = array();
        foreach ($writerNames as $writerName) {
            if (isset($classes[$writerName])) {
                $writers[$writerName] = $c->get($classes[$writerName]);
            }
        }
        return array_values($writers);
    }),

    'log.processors' => array(
        DI\link('Piwik\Log\Processor\ClassNameProcessor'),
        DI\link('Piwik\Log\Processor\RequestIdProcessor'),
        DI\link('Piwik\Log\Processor\ExceptionToTextProcessor'),
        DI\link('Piwik\Log\Processor\SprintfProcessor'),
        DI\link('Monolog\Processor\PsrLogMessageProcessor'),
    ),

    'Piwik\Log\Handler\FileHandler' => DI\object()
        ->constructor(DI\link('log.file.filename'), DI\link('log.level'))
        ->method('setFormatter', DI\link('Piwik\Log\Formatter\LineMessageFormatter')),

    'Piwik\Log\Handler\DatabaseHandler' => DI\object()
        ->constructor(DI\link('log.level'))
        ->method('setFormatter', DI\link('Piwik\Log\Formatter\LineMessageFormatter')),

    'Piwik\Log\Handler\WebNotificationHandler' => DI\object()
        ->constructor(DI\link('log.level'))
        ->method('setFormatter', DI\link('Piwik\Log\Formatter\LineMessageFormatter')),

    'log.level' => DI\factory(function (ContainerInterface $c) {
        if ($c->has('ini.log.log_level')) {
            $level = strtoupper($c->get('ini.log.log_level'));
            if (!empty($level) && defined('Piwik\Log::'.strtoupper($level))) {
                return Log::getMonologLevel(constant('Piwik\Log::'.strtoupper($level)));
            }
        }
        return Logger::WARNING;
    }),

    'log.file.filename' => DI\factory(function (ContainerInterface $c) {
        $logPath = $c->get('ini.log.logger_file_path');

        // Absolute path
        if (strpos($logPath, '/') === 0) {
            return $logPath;
        }

        // Remove 'tmp/' at the beginning
        if (strpos($logPath, 'tmp/') === 0) {
            $logPath = substr($logPath, strlen('tmp'));
        }

        if (empty($logPath)) {
            // Default log file
            $logPath = '/logs/piwik.log';
        }

        $logPath = $c->get('path.tmp') . $logPath;
        if (is_dir($logPath)) {
            $logPath .= '/piwik.log';
        }

        return $logPath;
    }),

    'Piwik\Log\Formatter\LineMessageFormatter' => DI\object()
        ->constructor(DI\link('log.format')),

    'log.format' => DI\factory(function (ContainerInterface $c) {
        if ($c->has('ini.log.string_message_format')) {
            return $c->get('ini.log.string_message_format');
        }
        return '%level% %tag%[%datetime%] %message%';
    }),

);
