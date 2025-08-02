<?php
/**
 * Rate Limit Attribute
 * 
 * Attribute for applying route-specific rate limiting configuration to HTTP routes.
 * This allows fine-grained control over rate limits on a per-route basis.
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit
{
    /**
     * Maximum requests per IP within the time window
     * @var int
     */
    protected int $maxRequests;

    /**
     * Time window in seconds
     * @var int
     */
    protected int $timeWindow;

    /**
     * Additional burst allowance requests
     * @var int
     */
    protected int $burstAllowance;

    /**
     * Whether to bypass global rate limiting for this route
     * @var bool
     */
    protected bool $bypassGlobal;

    /**
     * Custom whitelist of IP addresses for this route
     * @var array<string>
     */
    protected array $whitelist;

    /**
     * Create a new rate limit attribute
     * 
     * @param int $maxRequests Maximum requests per IP within the time window
     * @param int $timeWindow Time window in seconds (default: 60 seconds)
     * @param int $burstAllowance Additional burst allowance requests (default: 0)
     * @param bool $bypassGlobal Whether to bypass global rate limiting (default: false)
     * @param array<string> $whitelist Custom whitelist of IP addresses for this route
     */
    public function __construct(
        int $maxRequests,
        int $timeWindow = 60,
        int $burstAllowance = 0,
        bool $bypassGlobal = false,
        array $whitelist = []
    ) {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->burstAllowance = $burstAllowance;
        $this->bypassGlobal = $bypassGlobal;
        $this->whitelist = $whitelist;
    }

    /**
     * Get maximum requests per IP
     * 
     * @return int
     */
    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    /**
     * Get time window in seconds
     * 
     * @return int
     */
    public function getTimeWindow(): int
    {
        return $this->timeWindow;
    }

    /**
     * Get burst allowance
     * 
     * @return int
     */
    public function getBurstAllowance(): int
    {
        return $this->burstAllowance;
    }

    /**
     * Check if global rate limiting should be bypassed
     * 
     * @return bool
     */
    public function shouldBypassGlobal(): bool
    {
        return $this->bypassGlobal;
    }

    /**
     * Get custom whitelist for this route
     * 
     * @return array<string>
     */
    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    /**
     * Check if an IP is whitelisted for this route
     * 
     * @param string $ip IP address to check
     * @return bool
     */
    public function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->whitelist, true);
    }
}
