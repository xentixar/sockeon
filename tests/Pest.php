<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

function is_port_available(int $port): bool {
    $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($sock === false) {
        return false;
    }
    
    try {
        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        
        $result = @socket_bind($sock, '127.0.0.1', $port);
        
        if ($result !== false) {
            $listen = @socket_listen($sock);
        }
        
        return $result !== false && isset($listen) && $listen !== false;
    } finally {
        socket_close($sock);
    }
}

function get_test_port(int $min = 45000, int $max = 46000): int {
    $attempts = 0;
    $max_attempts = 10;
    
    do {
        $port = rand($min, $max);
        if (is_port_available($port)) {
            return $port;
        }
        $attempts++;
        usleep(100000);
    } while ($attempts < $max_attempts);
    
    throw new RuntimeException("Could not find an available port after $max_attempts attempts");
}

pest()
->extend(Tests\TestCase::class)
->beforeEach(function () {
    $port = get_test_port();
    $this->testPort = $port;
})
->afterEach(function () {
    if (isset($this->testPort)) {
        shell_exec("fuser -k {$this->testPort}/tcp 2>/dev/null");
        usleep(100000);
    }
})
->in('Feature', 'Unit', 'Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
