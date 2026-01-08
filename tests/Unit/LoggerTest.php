<?php

use Sockeon\Sockeon\Logging\Logger;
use Sockeon\Sockeon\Logging\LogLevel;

$tempLogDir = '';

beforeEach(function () use (&$tempLogDir) {
    $tempLogDir = sys_get_temp_dir() . '/sockeon_logger_test_' . uniqid();
    if (!file_exists($tempLogDir)) {
        mkdir($tempLogDir, 0755, true);
    }
});

afterEach(function () use (&$tempLogDir) {
    if (!empty($tempLogDir) && file_exists($tempLogDir)) {
        $files = glob($tempLogDir . '/*.*');
        if (is_array($files)) {
            array_map('unlink', $files);
        }

        $dirs = glob($tempLogDir . '/*', GLOB_ONLYDIR);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $subfiles = glob($dir . '/*.*');
                if (is_array($subfiles)) {
                    array_map('unlink', $subfiles);
                }
                rmdir($dir);
            }
        }

        rmdir($tempLogDir);
    }
});

test('logger can be instantiated with default config', function () {
    $logger = new Logger();
    expect($logger)->toBeInstanceOf(Logger::class);
});

test('logger can be instantiated with custom config', function () use (&$tempLogDir) {
    $logger = new Logger(
        minLogLevel: LogLevel::INFO,
        logToConsole: false,
        logToFile: true,
        logDirectory: $tempLogDir,
        separateLogFiles: true
    );

    expect($logger)->toBeInstanceOf(Logger::class);
});

test('logger respects minimum log level', function () {
    ob_start();

    $logger = new Logger(
        minLogLevel: LogLevel::WARNING,
        logToConsole: true,
        logToFile: false
    );

    $logger->debug('Debug message');
    $logger->info('Info message');
    $logger->warning('Warning message');
    $logger->error('Error message');

    $output = (string) ob_get_clean();

    expect(str_contains($output, 'Debug message'))->toBeFalse()
        ->and(str_contains($output, 'Info message'))->toBeFalse()
        ->and(str_contains($output, 'Warning message'))->toBeTrue()
        ->and(str_contains($output, 'Error message'))->toBeTrue();
});

test('logger can disable console output', function () {
    ob_start();

    $logger = new Logger(
        logToConsole: false,
        logToFile: false
    );

    $logger->info('This should not appear in console');

    $output = ob_get_clean();
    expect($output)->toBe('');
});

test('logger can write to log file', function () use (&$tempLogDir) {
    $logger = new Logger(
        logToConsole: false,
        logToFile: true,
        logDirectory: $tempLogDir
    );

    $testMessage = 'Test log message ' . uniqid();
    $logger->info($testMessage);

    // When separateLogFiles is false (default), no date suffix
    $logFile = "{$tempLogDir}/sockeon.log";

    expect(file_exists($logFile))->toBeTrue();

    $content = (string) file_get_contents($logFile);
    expect(str_contains($content, $testMessage))->toBeTrue()
        ->and(str_contains($content, 'INFO:'))->toBeTrue();
});

test('logger can create separate log files per level', function () use (&$tempLogDir) {
    $logger = new Logger(
        logToConsole: false,
        logToFile: true,
        logDirectory: $tempLogDir,
        separateLogFiles: true
    );

    $logger->debug('Debug message');
    $logger->info('Info message');
    $logger->warning('Warning message');

    $date = (new DateTime())->format('Y-m-d');

    $mainLogFile = "{$tempLogDir}/sockeon-{$date}.log";
    expect(file_exists($mainLogFile))->toBeTrue();

    $debugLogFile = "{$tempLogDir}/debug/{$date}.log";
    $infoLogFile = "{$tempLogDir}/info/{$date}.log";
    $warningLogFile = "{$tempLogDir}/warning/{$date}.log";

    expect(file_exists($debugLogFile))->toBeTrue()
        ->and(file_exists($infoLogFile))->toBeTrue()
        ->and(file_exists($warningLogFile))->toBeTrue();

    $debugContent = (string) file_get_contents($debugLogFile);
    expect(str_contains($debugContent, 'Debug message'))->toBeTrue();

    $infoContent = (string) file_get_contents($infoLogFile);
    expect(str_contains($infoContent, 'Info message'))->toBeTrue();

    $warningContent = (string) file_get_contents($warningLogFile);
    expect(str_contains($warningContent, 'Warning message'))->toBeTrue();
});

test('logger can log exceptions', function () use (&$tempLogDir) {
    $logger = new Logger(
        logToConsole: false,
        logToFile: true,
        logDirectory: $tempLogDir
    );

    $exception = new Exception('Test exception message');
    $logger->exception($exception);

    // When separateLogFiles is false (default), no date suffix
    $logFile = "{$tempLogDir}/sockeon.log";

    $content = (string) file_get_contents($logFile);
    expect(str_contains($content, 'Exception: Test exception message'))->toBeTrue()
        ->and(str_contains($content, 'File:'))->toBeTrue()
        ->and(str_contains($content, 'Code:'))->toBeTrue()
        ->and(str_contains($content, 'Trace:'))->toBeTrue();
});

test('logger can include context data', function () use (&$tempLogDir) {
    $logger = new Logger(
        logToConsole: false,
        logToFile: true,
        logDirectory: $tempLogDir
    );

    $context = [
        'user' => 'test_user',
        'action' => 'login',
        'ip' => '127.0.0.1',
    ];

    $logger->info('User action', $context);

    // When separateLogFiles is false (default), no date suffix
    $logFile = "{$tempLogDir}/sockeon.log";

    $content = (string) file_get_contents($logFile);
    expect(str_contains($content, 'User action'))->toBeTrue()
        ->and(str_contains($content, 'test_user'))->toBeTrue()
        ->and(str_contains($content, 'login'))->toBeTrue()
        ->and(str_contains($content, '127.0.0.1'))->toBeTrue();
});

test('logger supports all log levels', function () {
    ob_start();

    $logger = new Logger(
        logToConsole: true,
        logToFile: false
    );

    $logger->emergency('Emergency message');
    $logger->alert('Alert message');
    $logger->critical('Critical message');
    $logger->error('Error message');
    $logger->warning('Warning message');
    $logger->notice('Notice message');
    $logger->info('Info message');
    $logger->debug('Debug message');

    $output = (string) ob_get_clean();

    expect(str_contains($output, 'EMERGENCY: Emergency message'))->toBeTrue()
        ->and(str_contains($output, 'ALERT: Alert message'))->toBeTrue()
        ->and(str_contains($output, 'CRITICAL: Critical message'))->toBeTrue()
        ->and(str_contains($output, 'ERROR: Error message'))->toBeTrue()
        ->and(str_contains($output, 'WARNING: Warning message'))->toBeTrue()
        ->and(str_contains($output, 'NOTICE: Notice message'))->toBeTrue()
        ->and(str_contains($output, 'INFO: Info message'))->toBeTrue()
        ->and(str_contains($output, 'DEBUG: Debug message'))->toBeTrue();
});

test('logger setLogToConsole method works', function () {
    $logger = new Logger(
        logToConsole: true,
        logToFile: false
    );

    ob_start();
    $logger->info('First message');
    $output1 = (string) ob_get_clean();
    expect(str_contains($output1, 'First message'))->toBeTrue();

    $logger->setLogToConsole(false);

    ob_start();
    $logger->info('Second message');
    $output2 = ob_get_clean();
    expect($output2)->toBe('');
});

test('logger setLogToFile method works', function () use (&$tempLogDir) {
    $logger = new Logger(
        logToConsole: false,
        logToFile: false
    );

    $logger->setLogToFile(true);
    $logger->setLogDirectory($tempLogDir);

    $testMessage = 'File logging activated';
    $logger->info($testMessage);

    // When separateLogFiles is false (default), no date suffix
    $logFile = "{$tempLogDir}/sockeon.log";

    expect(file_exists($logFile))->toBeTrue();
    $fileContent = (string) file_get_contents($logFile);
    expect(str_contains($fileContent, $testMessage))->toBeTrue();
});
