<?php

namespace Sockeon\Sockeon\Config;

use Sockeon\Sockeon\Contracts\LoggerInterface;

class ServerConfig
{
    /**
     * The host the server will bind to.
     */
    protected string $host = '0.0.0.0';

    /**
     * The port the server will listen on.
     */
    protected int $port = 6001;

    /**
     * Enable or disable debug mode.
     */
    protected bool $debug = false;

    /**
     * CORS configuration
     * 
     * @var CorsConfig
     */
    protected CorsConfig $corsConfig;

    /**
     * Optional custom logger. If null, a default logger will be used.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Optional path to queue file.
     */
    protected ?string $queueFile = null;

    /**
     * Optional authentication key for securing connections.
     */
    protected ?string $authKey = null;

    /**
     * Rate limiting configuration. If null, rate limiting is disabled.
     */
    protected ?RateLimitConfig $rateLimitConfig = null;

    /**
     * Create a new server configuration instance
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if (isset($config['host']) && is_string($config['host'])) {
            $this->host = $config['host'];
        }

        if (isset($config['port']) && is_int($config['port'])) {
            $this->port = $config['port'];
        } elseif (isset($config['port']) && is_numeric($config['port'])) {
            $this->port = (int) $config['port'];
        }

        if (isset($config['debug'])) {
            if (is_bool($config['debug'])) {
                $this->debug = $config['debug'];
            } else {
                $this->debug = !empty($config['debug']);
            }
        }

        // Initialize CORS config
        if (isset($config['cors']) && $config['cors'] instanceof CorsConfig) {
            $this->corsConfig = $config['cors'];
        } elseif (isset($config['cors']) && is_array($config['cors'])) {
            /** @var array<string, mixed> $corsConfig */
            $corsConfig = $config['cors'];
            $this->corsConfig = new CorsConfig($corsConfig);
        } else {
            $this->corsConfig = new CorsConfig();
        }

        if (isset($config['logger']) && $config['logger'] instanceof LoggerInterface) {
            $this->logger = $config['logger'];
        }

        if (isset($config['queue_file']) && is_string($config['queue_file'])) {
            $this->queueFile = $config['queue_file'];
        }

        if (isset($config['auth_key']) && is_string($config['auth_key'])) {
            $this->authKey = $config['auth_key'];
        }

        // Initialize rate limit config
        if (isset($config['rate_limit']) && $config['rate_limit'] instanceof RateLimitConfig) {
            $this->rateLimitConfig = $config['rate_limit'];
        } elseif (isset($config['rate_limit']) && is_array($config['rate_limit'])) {
            /** @var array<string, mixed> $rateLimitConfig */
            $rateLimitConfig = $config['rate_limit'];
            $this->rateLimitConfig = new RateLimitConfig($rateLimitConfig);
        }
    }

    /**
     * Get the host
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Set the host
     *
     * @param string $host
     * @return void
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * Get the port
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Set the port
     *
     * @param int $port
     * @return void
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set debug mode
     *
     * @param bool $debug
     * @return void
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get CORS configuration
     *
     * @return CorsConfig
     */
    public function getCorsConfig(): CorsConfig
    {
        return $this->corsConfig;
    }

    /**
     * Set CORS configuration
     *
     * @param CorsConfig $corsConfig
     * @return void
     */
    public function setCorsConfig(CorsConfig $corsConfig): void
    {
        $this->corsConfig = $corsConfig;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface|null $logger
     * @return void
     */
    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the queue file path
     *
     * @return string|null
     */
    public function getQueueFile(): ?string
    {
        return $this->queueFile;
    }

    /**
     * Set the queue file path
     *
     * @param string|null $queueFile
     * @return void
     */
    public function setQueueFile(?string $queueFile): void
    {
        $this->queueFile = $queueFile;
    }

    /**
     * Get the authentication key
     *
     * @return string|null
     */
    public function getAuthKey(): ?string
    {
        return $this->authKey;
    }

    /**
     * Set the authentication key
     *
     * @param string|null $authKey
     * @return void
     */
    public function setAuthKey(?string $authKey): void
    {
        $this->authKey = $authKey;
    }

    /**
     * Get rate limit configuration
     *
     * @return RateLimitConfig|null
     */
    public function getRateLimitConfig(): ?RateLimitConfig
    {
        return $this->rateLimitConfig;
    }

    /**
     * Set rate limit configuration
     *
     * @param RateLimitConfig|null $rateLimitConfig
     * @return void
     */
    public function setRateLimitConfig(?RateLimitConfig $rateLimitConfig): void
    {
        $this->rateLimitConfig = $rateLimitConfig;
    }
}
