<?php
/**
 * HpackEncoder class
 * 
 * Implements HPACK header compression for HTTP/2
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

class HpackEncoder
{
    /**
     * Dynamic table
     * @var array<int, array{name: string, value: string}>
     */
    protected array $dynamicTable = [];

    /**
     * Dynamic table size
     * @var int
     */
    protected int $dynamicTableSize = 0;

    /**
     * Max dynamic table size
     * @var int
     */
    protected int $maxDynamicTableSize = 4096;

    /**
     * Static table (RFC 7541 Appendix B)
     * @var array<int, array{name: string, value: string}>
     */
    protected static array $staticTable = [
        1 => ['name' => ':authority', 'value' => ''],
        2 => ['name' => ':method', 'value' => 'GET'],
        3 => ['name' => ':method', 'value' => 'POST'],
        4 => ['name' => ':path', 'value' => '/'],
        5 => ['name' => ':path', 'value' => '/index.html'],
        6 => ['name' => ':scheme', 'value' => 'http'],
        7 => ['name' => ':scheme', 'value' => 'https'],
        8 => ['name' => ':status', 'value' => '200'],
        9 => ['name' => ':status', 'value' => '204'],
        10 => ['name' => ':status', 'value' => '206'],
        11 => ['name' => ':status', 'value' => '304'],
        12 => ['name' => ':status', 'value' => '400'],
        13 => ['name' => ':status', 'value' => '404'],
        14 => ['name' => ':status', 'value' => '500'],
        15 => ['name' => 'accept-charset', 'value' => ''],
        16 => ['name' => 'accept-encoding', 'value' => 'gzip, deflate'],
        17 => ['name' => 'accept-language', 'value' => ''],
        18 => ['name' => 'accept-ranges', 'value' => ''],
        19 => ['name' => 'accept', 'value' => ''],
        20 => ['name' => 'access-control-allow-origin', 'value' => ''],
        21 => ['name' => 'age', 'value' => ''],
        22 => ['name' => 'allow', 'value' => ''],
        23 => ['name' => 'authorization', 'value' => ''],
        24 => ['name' => 'cache-control', 'value' => ''],
        25 => ['name' => 'content-disposition', 'value' => ''],
        26 => ['name' => 'content-encoding', 'value' => ''],
        27 => ['name' => 'content-language', 'value' => ''],
        28 => ['name' => 'content-length', 'value' => ''],
        29 => ['name' => 'content-location', 'value' => ''],
        30 => ['name' => 'content-range', 'value' => ''],
        31 => ['name' => 'content-type', 'value' => ''],
        32 => ['name' => 'cookie', 'value' => ''],
        33 => ['name' => 'date', 'value' => ''],
        34 => ['name' => 'etag', 'value' => ''],
        35 => ['name' => 'expect', 'value' => ''],
        36 => ['name' => 'expires', 'value' => ''],
        37 => ['name' => 'from', 'value' => ''],
        38 => ['name' => 'host', 'value' => ''],
        39 => ['name' => 'if-match', 'value' => ''],
        40 => ['name' => 'if-modified-since', 'value' => ''],
        41 => ['name' => 'if-none-match', 'value' => ''],
        42 => ['name' => 'if-range', 'value' => ''],
        43 => ['name' => 'if-unmodified-since', 'value' => ''],
        44 => ['name' => 'last-modified', 'value' => ''],
        45 => ['name' => 'link', 'value' => ''],
        46 => ['name' => 'location', 'value' => ''],
        47 => ['name' => 'max-forwards', 'value' => ''],
        48 => ['name' => 'proxy-authenticate', 'value' => ''],
        49 => ['name' => 'proxy-authorization', 'value' => ''],
        50 => ['name' => 'range', 'value' => ''],
        51 => ['name' => 'referer', 'value' => ''],
        52 => ['name' => 'refresh', 'value' => ''],
        53 => ['name' => 'retry-after', 'value' => ''],
        54 => ['name' => 'server', 'value' => ''],
        55 => ['name' => 'set-cookie', 'value' => ''],
        56 => ['name' => 'strict-transport-security', 'value' => ''],
        57 => ['name' => 'transfer-encoding', 'value' => ''],
        58 => ['name' => 'user-agent', 'value' => ''],
        59 => ['name' => 'vary', 'value' => ''],
        60 => ['name' => 'via', 'value' => ''],
        61 => ['name' => 'www-authenticate', 'value' => '']
    ];

    /**
     * Encode headers to HPACK format
     * 
     * @param array<string, string> $headers
     * @return string
     */
    public function encode(array $headers): string
    {
        $encoded = '';

        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            $encoded .= $this->encodeHeader($name, $value);
        }

        return $encoded;
    }

    /**
     * Encode a single header
     * 
     * @param string $name
     * @param string $value
     * @return string
     */
    protected function encodeHeader(string $name, string $value): string
    {
        // Try to find in static table
        $staticIndex = $this->findInStaticTable($name, $value);
        if ($staticIndex !== null) {
            if (self::$staticTable[$staticIndex]['value'] === $value) {
                // Full match - indexed header field
                return $this->encodeInteger($staticIndex, 7, 0x80);
            } else {
                // Name match only - literal header field with incremental indexing
                $encoded = $this->encodeInteger($staticIndex, 6, 0x40);
                $encoded .= $this->encodeString($value);
                $this->addToDynamicTable($name, $value);
                return $encoded;
            }
        }

        // Try to find in dynamic table
        $dynamicIndex = $this->findInDynamicTable($name, $value);
        if ($dynamicIndex !== null) {
            $tableIndex = count(self::$staticTable) + $dynamicIndex;
            if ($this->dynamicTable[$dynamicIndex]['value'] === $value) {
                // Full match - indexed header field
                return $this->encodeInteger($tableIndex, 7, 0x80);
            } else {
                // Name match only - literal header field with incremental indexing
                $encoded = $this->encodeInteger($tableIndex, 6, 0x40);
                $encoded .= $this->encodeString($value);
                $this->addToDynamicTable($name, $value);
                return $encoded;
            }
        }

        // No match - literal header field with incremental indexing (new name)
        $encoded = chr(0x40); // Pattern: 01
        $encoded .= $this->encodeString($name);
        $encoded .= $this->encodeString($value);
        $this->addToDynamicTable($name, $value);

        return $encoded;
    }

    /**
     * Find header in static table
     * 
     * @param string $name
     * @param string $value
     * @return int|null
     */
    protected function findInStaticTable(string $name, string $value): ?int
    {
        foreach (self::$staticTable as $index => $entry) {
            if ($entry['name'] === $name) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Find header in dynamic table
     * 
     * @param string $name
     * @param string $value
     * @return int|null
     */
    protected function findInDynamicTable(string $name, string $value): ?int
    {
        foreach ($this->dynamicTable as $index => $entry) {
            if ($entry['name'] === $name) {
                return $index + 1; // Dynamic table is 1-indexed
            }
        }
        return null;
    }

    /**
     * Add entry to dynamic table
     * 
     * @param string $name
     * @param string $value
     * @return void
     */
    protected function addToDynamicTable(string $name, string $value): void
    {
        $entrySize = strlen($name) + strlen($value) + 32; // RFC 7541 Section 4.1

        // Evict entries if necessary
        while ($this->dynamicTableSize + $entrySize > $this->maxDynamicTableSize && !empty($this->dynamicTable)) {
            $evicted = array_pop($this->dynamicTable);
            $this->dynamicTableSize -= strlen($evicted['name']) + strlen($evicted['value']) + 32;
        }

        // Add new entry to the beginning
        array_unshift($this->dynamicTable, ['name' => $name, 'value' => $value]);
        $this->dynamicTableSize += $entrySize;
    }

    /**
     * Encode integer with prefix
     * 
     * @param int $value
     * @param int $prefixBits
     * @param int $pattern
     * @return string
     */
    protected function encodeInteger(int $value, int $prefixBits, int $pattern): string
    {
        $maxValue = (1 << $prefixBits) - 1;

        if ($value < $maxValue) {
            return chr($pattern | $value);
        }

        $encoded = chr($pattern | $maxValue);
        $value -= $maxValue;

        while ($value >= 128) {
            $encoded .= chr(($value % 128) + 128);
            $value = intval($value / 128);
        }

        $encoded .= chr($value);
        return $encoded;
    }

    /**
     * Encode string (with Huffman coding if beneficial)
     * 
     * @param string $string
     * @return string
     */
    protected function encodeString(string $string): string
    {
        // Simple implementation without Huffman coding
        $length = strlen($string);
        return $this->encodeInteger($length, 7, 0x00) . $string;
    }

    /**
     * Set max dynamic table size
     * 
     * @param int $size
     * @return void
     */
    public function setMaxDynamicTableSize(int $size): void
    {
        $this->maxDynamicTableSize = $size;

        // Evict entries if current size exceeds new max
        while ($this->dynamicTableSize > $this->maxDynamicTableSize && !empty($this->dynamicTable)) {
            $evicted = array_pop($this->dynamicTable);
            $this->dynamicTableSize -= strlen($evicted['name']) + strlen($evicted['value']) + 32;
        }
    }

    /**
     * Get current dynamic table size
     * 
     * @return int
     */
    public function getDynamicTableSize(): int
    {
        return $this->dynamicTableSize;
    }

    /**
     * Get max dynamic table size
     * 
     * @return int
     */
    public function getMaxDynamicTableSize(): int
    {
        return $this->maxDynamicTableSize;
    }

    /**
     * Clear dynamic table
     * 
     * @return void
     */
    public function clearDynamicTable(): void
    {
        $this->dynamicTable = [];
        $this->dynamicTableSize = 0;
    }
}
