<?php

use Sockeon\Sockeon\Core\Server;
use Sockeon\Sockeon\Logging\Logger;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->beforeEach(function () {
        /**
         * @var int $port
         * @var Socket $reservedSocket
         */
        [$port, $reservedSocket] = reserveTestPort();
        socket_close($reservedSocket);

        $logger = new Logger();
        $logger->setLogToConsole(false);

        $this->server = new Server( //@phpstan-ignore-line
            host: '127.0.0.1',
            port: $port,
            logger: $logger,
        );
    })
    ->in('Feature', 'Unit', 'Integration');

/**
 * @param int $min
 * @param int $max
 * @return array<int, int|Socket>
 */
function reserveTestPort(int $min = 45000, int $max = 46000): array
{
    $attempts = 0;
    $max_attempts = 10;

    do {
        $port = rand($min, $max);
        $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($sock && @socket_bind($sock, '127.0.0.1', $port)) {
            return [$port, $sock];
        }

        $attempts++;
        usleep(100000);
    } while ($attempts < $max_attempts);

    throw new RuntimeException("Could not reserve a test port after $max_attempts attempts");
}
