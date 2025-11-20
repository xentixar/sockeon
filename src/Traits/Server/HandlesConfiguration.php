<?php

namespace Sockeon\Sockeon\Traits\Server;

use Sockeon\Sockeon\Config\CorsConfig;
use Sockeon\Sockeon\Config\ServerConfig;
use Sockeon\Sockeon\Core\Config;
use Sockeon\Sockeon\Core\Middleware;
use Sockeon\Sockeon\Core\NamespaceManager;
use Sockeon\Sockeon\Core\Router;
use Sockeon\Sockeon\Http\Handler as HttpHandler;
use Sockeon\Sockeon\WebSocket\Handler as WebSocketHandler;
use Sockeon\Sockeon\Logging\Logger;
use Sockeon\Sockeon\Logging\LogLevel;

trait HandlesConfiguration
{
    protected function applyServerConfig(ServerConfig $config): void
    {
        $this->host = $config->getHost();
        $this->port = $config->getPort();
        $this->isDebug = $config->isDebug();

        Config::init();

        if ($config->getQueueFile()) {
            Config::setQueueFile($config->getQueueFile());
        }

        if ($config->getAuthKey() !== null) {
            Config::setAuthKey($config->getAuthKey());
        }

        // Apply proxy configuration
        Config::setTrustProxy($config->getTrustProxy());
        if ($config->getProxyHeaders() !== null) {
            Config::setProxyHeaders($config->getProxyHeaders());
        }

        // Store health check path
        $this->healthCheckPath = $config->getHealthCheckPath();

        $this->rateLimitConfig = $config->getRateLimitConfig();

        $this->logger = $config->getLogger() ?? new Logger(
            minLogLevel: $this->isDebug ? LogLevel::DEBUG : LogLevel::INFO,
            logToConsole: true,
            logToFile: false,
            logDirectory: null,
            separateLogFiles: false
        );
    }

    protected function initializeCoreComponents(ServerConfig $config): void
    {
        $this->router = new Router();
        $this->wsHandler = new WebSocketHandler($this, $this->resolveAllowedOrigins($config->getCorsConfig()));
        $this->httpHandler = new HttpHandler($this, $config->getCorsConfig());
        $this->namespaceManager = new NamespaceManager();
        $this->middleware = new Middleware();

        if ($this->rateLimitConfig && $this->rateLimitConfig->isEnabled()) {
            $this->middleware->addHttpMiddleware(\Sockeon\Sockeon\Http\Middleware\HttpRateLimitMiddleware::class);
            $this->middleware->addWebSocketMiddleware(\Sockeon\Sockeon\WebSocket\Middleware\WebSocketRateLimitMiddleware::class);
        }
    }

    /**
     * @param CorsConfig $cors
     * @return array<int, string>
     */
    protected function resolveAllowedOrigins(CorsConfig $cors): array
    {
        $origins = [];
        foreach ($cors->getAllowedOrigins() as $origin) {
            if (is_string($origin)) { //@phpstan-ignore-line
                $origins[] = $origin;
            }
        }

        return $origins ?: ['*'];
    }
}
