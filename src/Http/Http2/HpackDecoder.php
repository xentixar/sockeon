<?php
/**
 * HpackDecoder class
 * 
 * Implements HPACK header decompression for HTTP/2
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http\Http2;

class HpackDecoder
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
     * Static table (same as HpackEncoder)
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
     * Decode HPACK-encoded headers
     * 
     * @param string $data
     * @return array<string, string>
     */
    public function decode(string $data): array
    {
        $headers = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            $result = $this->decodeHeader($data, $offset);
            if ($result === null) {
                break;
            }

            [$name, $value] = $result;
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Decode a single header field
     * 
     * @param string $data
     * @param int $offset
     * @return array{0: string, 1: string}|null
     */
    protected function decodeHeader(string $data, int &$offset): ?array
    {
        if ($offset >= strlen($data)) {
            return null;
        }

        $byte = ord($data[$offset]);

        if ($byte & 0x80) {
            // Indexed Header Field (1xxxxxxx)
            $index = $this->decodeInteger($data, $offset, 7);
            return $this->getIndexedHeader($index);
        } elseif ($byte & 0x40) {
            // Literal Header Field with Incremental Indexing (01xxxxxx)
            $index = $this->decodeInteger($data, $offset, 6);
            if ($index === 0) {
                // New name
                $name = $this->decodeString($data, $offset);
                $value = $this->decodeString($data, $offset);
            } else {
                // Indexed name
                $nameEntry = $this->getIndexedHeader($index);
                if ($nameEntry === null) {
                    return null;
                }
                $name = $nameEntry[0];
                $value = $this->decodeString($data, $offset);
            }
            $this->addToDynamicTable($name, $value);
            return [$name, $value];
        } elseif ($byte & 0x20) {
            // Dynamic Table Size Update (001xxxxx)
            $newSize = $this->decodeInteger($data, $offset, 5);
            $this->setMaxDynamicTableSize($newSize);
            return $this->decodeHeader($data, $offset); // Process next header
        } else {
            // Literal Header Field without Indexing (0000xxxx) or Never Indexed (0001xxxx)
            $index = $this->decodeInteger($data, $offset, 4);
            if ($index === 0) {
                // New name
                $name = $this->decodeString($data, $offset);
                $value = $this->decodeString($data, $offset);
            } else {
                // Indexed name
                $nameEntry = $this->getIndexedHeader($index);
                if ($nameEntry === null) {
                    return null;
                }
                $name = $nameEntry[0];
                $value = $this->decodeString($data, $offset);
            }
            return [$name, $value];
        }
    }

    /**
     * Get header by index from static or dynamic table
     * 
     * @param int $index
     * @return array{0: string, 1: string}|null
     */
    protected function getIndexedHeader(int $index): ?array
    {
        if ($index === 0) {
            return null;
        }

        $staticTableSize = count(self::$staticTable);

        if ($index <= $staticTableSize) {
            // Static table
            $entry = self::$staticTable[$index];
            return [$entry['name'], $entry['value']];
        } else {
            // Dynamic table
            $dynamicIndex = $index - $staticTableSize - 1;
            if (isset($this->dynamicTable[$dynamicIndex])) {
                $entry = $this->dynamicTable[$dynamicIndex];
                return [$entry['name'], $entry['value']];
            }
        }

        return null;
    }

    /**
     * Decode integer with prefix
     * 
     * @param string $data
     * @param int $offset
     * @param int $prefixBits
     * @return int
     */
    protected function decodeInteger(string $data, int &$offset, int $prefixBits): int
    {
        $maxValue = (1 << $prefixBits) - 1;
        $byte = ord($data[$offset]);
        $offset++;

        $value = $byte & $maxValue;

        if ($value < $maxValue) {
            return $value;
        }

        $m = 0;
        while ($offset < strlen($data)) {
            $byte = ord($data[$offset]);
            $offset++;

            $value += ($byte & 0x7F) * (1 << $m);
            $m += 7;

            if (($byte & 0x80) === 0) {
                break;
            }
        }

        return $value;
    }

    /**
     * Decode string (with Huffman decoding if needed)
     * 
     * @param string $data
     * @param int $offset
     * @return string
     */
    protected function decodeString(string $data, int &$offset): string
    {
        $byte = ord($data[$offset]);
        $isHuffman = ($byte & 0x80) !== 0;

        $length = $this->decodeInteger($data, $offset, 7);
        $string = substr($data, $offset, $length);
        $offset += $length;

        if ($isHuffman) {
            // Simple implementation - in practice, you'd implement Huffman decoding
            // For now, we'll just return the string as-is
            return $string;
        }

        return $string;
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
