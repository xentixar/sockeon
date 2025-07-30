<?php
/**
 * Http2Handler class
 * 
 * Handles HTTP/2 protocol implementation, frame parsing, stream management,
 * and server push capabilities
 * 
 * Features:
 * - HTTP/2 frame parsing and generation
 * - Stream multiplexing and management
 * - HPACK header compression
 * - Server push functionality
 * - Flow control management
 * 
 * @package     Sockeon\Sockeon
 * @author      Sockeon
 * @copyright   Copyright (c) 2025
 */

namespace Sockeon\Sockeon\Http;

use InvalidArgumentException;
use Sockeon\Sockeon\Connection\Server;
use Sockeon\Sockeon\Http\Http2\Frame;
use Sockeon\Sockeon\Http\Http2\FrameType;
use Sockeon\Sockeon\Http\Http2\HpackDecoder;
use Sockeon\Sockeon\Http\Http2\HpackEncoder;
use Sockeon\Sockeon\Http\Http2\StreamManager;
use Sockeon\Sockeon\Http\Http2\Stream;
use Sockeon\Sockeon\Http\Http2\Settings;
use Sockeon\Sockeon\Http\Http2\Http2Request;
use Sockeon\Sockeon\Http\Http2\ServerPush;
use Sockeon\Sockeon\Http\Request;
use Sockeon\Sockeon\Http\Response;
use Throwable;

class Http2Handler
{
    /**
     * Reference to the server instance
     * @var Server
     */
    protected Server $server;

    /**
     * Stream manager for handling multiplexing
     * @var StreamManager
     */
    protected StreamManager $streamManager;

    /**
     * HPACK encoder for header compression
     * @var HpackEncoder
     */
    protected HpackEncoder $hpackEncoder;

    /**
     * HPACK decoder for header decompression
     * @var HpackDecoder
     */
    protected HpackDecoder $hpackDecoder;

    /**
     * HTTP/2 settings for the connection
     * @var Settings
     */
    protected Settings $settings;

    /**
     * Connection preface sent flag
     * @var bool
     */
    protected bool $prefaceSent = false;

    /**
     * Client connection settings received
     * @var bool
     */
    protected bool $settingsReceived = false;

    /**
     * Flow control window size
     * @var int
     */
    protected int $connectionWindowSize = 65535;

    /**
     * HTTP/2 connection preface
     */
    public const CONNECTION_PREFACE = "PRI * HTTP/2.0\r\n\r\nSM\r\n\r\n";

    /**
     * Constructor
     * 
     * @param Server $server The server instance
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
        $this->streamManager = new StreamManager();
        $this->hpackEncoder = new HpackEncoder();
        $this->hpackDecoder = new HpackDecoder();
        $this->settings = new Settings();
    }

    /**
     * Handle an incoming HTTP/2 connection
     * 
     * @param int $clientId The client identifier
     * @param resource $client The client socket resource
     * @param string $data The raw HTTP/2 data
     * @return void
     */
    public function handle(int $clientId, $client, string $data): void
    {
        try {
            $this->debug("Received HTTP/2 data from client #{$clientId}", ['dataLength' => strlen($data)]);

            if (!$this->prefaceSent) {
                $this->handleConnectionPreface($clientId, $client, $data);
                return;
            }

            // Parse frames from the data
            $frames = $this->parseFrames($data);

            foreach ($frames as $frame) {
                $this->handleFrame($clientId, $client, $frame);
            }

        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'context' => 'Http2Handler::handle']);
            $this->sendGoAwayFrame($client, 2); // INTERNAL_ERROR
        }
    }

    /**
     * Handle the HTTP/2 connection preface
     * 
     * @param int $clientId
     * @param resource $client
     * @param string $data
     * @return void
     */
    protected function handleConnectionPreface(int $clientId, $client, string $data): void
    {
        if (strpos($data, self::CONNECTION_PREFACE) === 0) {
            $this->debug("Valid HTTP/2 connection preface received from client #{$clientId}");
            
            // Send settings frame
            $settingsFrame = $this->settings->createSettingsFrame();
            fwrite($client, $settingsFrame);
            
            // Send settings ACK
            $settingsAckFrame = Frame::createSettingsAck();
            fwrite($client, $settingsAckFrame->toBinary());
            
            $this->prefaceSent = true;
            
            // Process any remaining data after preface
            $remainingData = substr($data, strlen(self::CONNECTION_PREFACE));
            if (!empty($remainingData)) {
                $this->handle($clientId, $client, $remainingData);
            }
        } else {
            throw new InvalidArgumentException('Invalid HTTP/2 connection preface');
        }
    }

    /**
     * Parse HTTP/2 frames from raw data
     * 
     * @param string $data
     * @return Frame[]
     */
    protected function parseFrames(string $data): array
    {
        $frames = [];
        $offset = 0;
        $dataLength = strlen($data);

        while ($offset < $dataLength) {
            if ($dataLength - $offset < 9) {
                // Not enough data for a complete frame header
                break;
            }

            $frame = Frame::parse($data, $offset);
            if ($frame === null) {
                break;
            }

            $frames[] = $frame;
            $offset += 9 + $frame->getLength(); // Header (9 bytes) + payload
        }

        return $frames;
    }

    /**
     * Handle a single HTTP/2 frame
     * 
     * @param int $clientId
     * @param resource $client
     * @param Frame $frame
     * @return void
     */
    protected function handleFrame(int $clientId, $client, Frame $frame): void
    {
        $this->debug("Handling frame", [
            'type' => $frame->getType(),
            'streamId' => $frame->getStreamId(),
            'length' => $frame->getLength()
        ]);

        switch ($frame->getType()) {
            case FrameType::SETTINGS:
                $this->handleSettingsFrame($client, $frame);
                break;

            case FrameType::HEADERS:
                $this->handleHeadersFrame($clientId, $client, $frame);
                break;

            case FrameType::DATA:
                $this->handleDataFrame($clientId, $client, $frame);
                break;

            case FrameType::WINDOW_UPDATE:
                $this->handleWindowUpdateFrame($frame);
                break;

            case FrameType::RST_STREAM:
                $this->handleRstStreamFrame($frame);
                break;

            case FrameType::PING:
                $this->handlePingFrame($client, $frame);
                break;

            case FrameType::GOAWAY:
                $this->handleGoAwayFrame($frame);
                break;

            default:
                $this->debug("Unknown frame type: " . $frame->getType());
        }
    }

    /**
     * Handle SETTINGS frame
     * 
     * @param resource $client
     * @param Frame $frame
     * @return void
     */
    protected function handleSettingsFrame($client, Frame $frame): void
    {
        if ($frame->hasAckFlag()) {
            $this->debug("Received SETTINGS ACK");
            return;
        }

        $this->settings->processSettingsFrame($frame);
        $this->settingsReceived = true;

        // Send settings ACK
        $ackFrame = Frame::createSettingsAck();
        fwrite($client, $ackFrame->toBinary());

        $this->debug("Processed SETTINGS frame and sent ACK");
    }

    /**
     * Handle HEADERS frame
     * 
     * @param int $clientId
     * @param resource $client
     * @param Frame $frame
     * @return void
     */
    protected function handleHeadersFrame(int $clientId, $client, Frame $frame): void
    {
        $streamId = $frame->getStreamId();
        
        // Get or create stream
        $stream = $this->streamManager->getStream($streamId);
        if ($stream === null) {
            $stream = $this->streamManager->createStream($streamId);
        }

        // Decode headers using HPACK
        $headerBlock = $frame->getPayload();
        $headers = $this->hpackDecoder->decode($headerBlock);
        
        $stream->addHeaders($headers);

        if ($frame->hasEndHeadersFlag()) {
            // Headers complete, create request and process
            $request = $this->createRequestFromStream($stream);
            $this->processHttp2Request($clientId, $client, $stream, $request);
        }

        if ($frame->hasEndStreamFlag()) {
            $stream->setState(Stream::STATE_HALF_CLOSED_REMOTE);
        }
    }

    /**
     * Handle DATA frame
     * 
     * @param int $clientId
     * @param resource $client
     * @param Frame $frame
     * @return void
     */
    protected function handleDataFrame(int $clientId, $client, Frame $frame): void
    {
        $streamId = $frame->getStreamId();
        $stream = $this->streamManager->getStream($streamId);

        if ($stream === null) {
            $this->sendRstStreamFrame($client, $streamId, 1); // PROTOCOL_ERROR
            return;
        }

        $stream->addData($frame->getPayload());

        if ($frame->hasEndStreamFlag()) {
            $stream->setState(Stream::STATE_HALF_CLOSED_REMOTE);
            
            // Process complete request if headers were already received
            if ($stream->hasCompleteHeaders()) {
                $request = $this->createRequestFromStream($stream);
                $this->processHttp2Request($clientId, $client, $stream, $request);
            }
        }

        // Send window update to maintain flow control
        $this->sendWindowUpdate($client, $streamId, $frame->getLength());
    }

    /**
     * Handle WINDOW_UPDATE frame
     * 
     * @param Frame $frame
     * @return void
     */
    protected function handleWindowUpdateFrame(Frame $frame): void
    {
        $streamId = $frame->getStreamId();
        $increment = unpack('N', $frame->getPayload())[1];

        if ($streamId === 0) {
            // Connection-level window update
            $this->connectionWindowSize += $increment;
        } else {
            // Stream-level window update
            $stream = $this->streamManager->getStream($streamId);
            if ($stream !== null) {
                $stream->updateSendWindow($increment);
            }
        }

        $this->debug("Window update", ['streamId' => $streamId, 'increment' => $increment]);
    }

    /**
     * Handle RST_STREAM frame
     * 
     * @param Frame $frame
     * @return void
     */
    protected function handleRstStreamFrame(Frame $frame): void
    {
        $streamId = $frame->getStreamId();
        $errorCode = unpack('N', $frame->getPayload())[1];

        $this->streamManager->closeStream($streamId);
        $this->debug("Stream reset", ['streamId' => $streamId, 'errorCode' => $errorCode]);
    }

    /**
     * Handle PING frame
     * 
     * @param resource $client
     * @param Frame $frame
     * @return void
     */
    protected function handlePingFrame($client, Frame $frame): void
    {
        if (!$frame->hasAckFlag()) {
            // Send PING ACK with same payload
            $pingAck = Frame::createPingAck($frame->getPayload());
            fwrite($client, $pingAck->toBinary());
        }
    }

    /**
     * Handle GOAWAY frame
     * 
     * @param Frame $frame
     * @return void
     */
    protected function handleGoAwayFrame(Frame $frame): void
    {
        $payload = $frame->getPayload();
        $lastStreamId = unpack('N', substr($payload, 0, 4))[1];
        $errorCode = unpack('N', substr($payload, 4, 4))[1];

        $this->debug("Received GOAWAY", ['lastStreamId' => $lastStreamId, 'errorCode' => $errorCode]);
        
        // Close all streams with ID > lastStreamId
        $this->streamManager->closeStreamsAfter($lastStreamId);
    }

    /**
     * Create HTTP Request from HTTP/2 stream
     * 
     * @param Stream $stream
     * @return Http2Request
     */
    protected function createRequestFromStream(Stream $stream): Http2Request
    {
        $headers = $stream->getHeaders();
        $method = $headers[':method'] ?? 'GET';
        $path = $headers[':path'] ?? '/';
        $scheme = $headers[':scheme'] ?? 'https';
        $authority = $headers[':authority'] ?? '';

        // Remove pseudo headers
        $httpHeaders = array_filter($headers, fn($key) => !str_starts_with($key, ':'), ARRAY_FILTER_USE_KEY);

        $query = [];
        $url = parse_url($path);
        if (isset($url['query'])) {
            parse_str($url['query'], $query);
            $path = $url['path'] ?? '/';
        }

        $body = $stream->getData();
        if (!empty($body)) {
            $decodedBody = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $body = $decodedBody;
            }
        }

        return new Http2Request([
            'method' => $method,
            'path' => $path,
            'protocol' => 'HTTP/2.0',
            'headers' => $httpHeaders,
            'query' => $query,
            'body' => $body
        ], $stream->getId());
    }

    /**
     * Process HTTP/2 request and send response
     * 
     * @param int $clientId
     * @param resource $client
     * @param Stream $stream
     * @param Http2Request $request
     * @return void
     */
    protected function processHttp2Request(int $clientId, $client, Stream $stream, Http2Request $request): void
    {
        try {
            $this->debug("Processing HTTP/2 request", [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
                'streamId' => $stream->getId()
            ]);

            // Set up server push for the request
            $serverPush = new ServerPush($this, $client, $stream->getId());
            $request->setServerPush($serverPush);

            $result = $this->server->getRouter()->dispatchHttp($request);

            if ($result instanceof Response) {
                $response = $result;
            } elseif ($result !== null) {
                if (is_array($result) || is_object($result)) {
                    $response = Response::json($result);
                } else {
                    $response = new Response($result);
                }
            } else {
                $response = Response::notFound();
            }

            $this->sendHttp2Response($client, $stream, $response);

        } catch (Throwable $e) {
            $this->server->getLogger()->exception($e, ['clientId' => $clientId, 'streamId' => $stream->getId()]);
            $this->sendRstStreamFrame($client, $stream->getId(), 2); // INTERNAL_ERROR
        }
    }

    /**
     * Send HTTP/2 response
     * 
     * @param resource $client
     * @param Stream $stream
     * @param Response $response
     * @return void
     */
    protected function sendHttp2Response($client, Stream $stream, Response $response): void
    {
        $streamId = $stream->getId();

        // Prepare headers
        $headers = [
            ':status' => (string)$response->getStatusCode(),
        ];

        foreach ($response->getHeaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }

        // Encode headers using HPACK
        $headerBlock = $this->hpackEncoder->encode($headers);

        // Send HEADERS frame
        $headersFrame = Frame::createHeaders($streamId, $headerBlock, true, false);
        fwrite($client, $headersFrame->toBinary());

        // Send body if present
        $body = $response->getBody();
        if ($body !== null && $body !== '') {
            if (is_array($body) || is_object($body)) {
                $body = json_encode($body);
            }

            $dataFrame = Frame::createData($streamId, $body, true);
            fwrite($client, $dataFrame->toBinary());
        }

        $stream->setState(Stream::STATE_CLOSED);
        $this->debug("Sent HTTP/2 response", ['streamId' => $streamId, 'status' => $response->getStatusCode()]);
    }

    /**
     * Send server push for a resource
     * 
     * @param resource $client
     * @param int $parentStreamId
     * @param string $method
     * @param string $path
     * @param array $headers
     * @param mixed $body
     * @return void
     */
    public function sendServerPush($client, int $parentStreamId, string $method, string $path, array $headers = [], $body = null): void
    {
        $pushStreamId = $this->streamManager->getNextStreamId();

        // Create PUSH_PROMISE frame
        $promiseHeaders = [
            ':method' => $method,
            ':path' => $path,
            ':scheme' => 'https',
            ':authority' => $headers['host'] ?? 'localhost'
        ];

        $promiseHeaderBlock = $this->hpackEncoder->encode($promiseHeaders);
        $pushPromiseFrame = Frame::createPushPromise($parentStreamId, $pushStreamId, $promiseHeaderBlock);
        fwrite($client, $pushPromiseFrame->toBinary());

        // Create response headers
        $responseHeaders = [
            ':status' => '200',
            'content-type' => $headers['content-type'] ?? 'text/html'
        ];

        $responseHeaderBlock = $this->hpackEncoder->encode($responseHeaders);
        $headersFrame = Frame::createHeaders($pushStreamId, $responseHeaderBlock, true, $body === null);
        fwrite($client, $headersFrame->toBinary());

        // Send body if present
        if ($body !== null) {
            if (is_array($body) || is_object($body)) {
                $body = json_encode($body);
            }

            $dataFrame = Frame::createData($pushStreamId, $body, true);
            fwrite($client, $dataFrame->toBinary());
        }

        $this->debug("Sent server push", [
            'parentStreamId' => $parentStreamId,
            'pushStreamId' => $pushStreamId,
            'path' => $path
        ]);
    }

    /**
     * Send RST_STREAM frame
     * 
     * @param resource $client
     * @param int $streamId
     * @param int $errorCode
     * @return void
     */
    protected function sendRstStreamFrame($client, int $streamId, int $errorCode): void
    {
        $rstFrame = Frame::createRstStream($streamId, $errorCode);
        fwrite($client, $rstFrame->toBinary());
    }

    /**
     * Send GOAWAY frame
     * 
     * @param resource $client
     * @param int $errorCode
     * @return void
     */
    protected function sendGoAwayFrame($client, int $errorCode): void
    {
        $lastStreamId = $this->streamManager->getLastStreamId();
        $goAwayFrame = Frame::createGoAway($lastStreamId, $errorCode);
        fwrite($client, $goAwayFrame->toBinary());
    }

    /**
     * Send WINDOW_UPDATE frame
     * 
     * @param resource $client
     * @param int $streamId
     * @param int $increment
     * @return void
     */
    protected function sendWindowUpdate($client, int $streamId, int $increment): void
    {
        $windowUpdateFrame = Frame::createWindowUpdate($streamId, $increment);
        fwrite($client, $windowUpdateFrame->toBinary());
    }

    /**
     * Get stream manager
     * 
     * @return StreamManager
     */
    public function getStreamManager(): StreamManager
    {
        return $this->streamManager;
    }

    /**
     * Log debug information if debug mode is enabled
     * 
     * @param string $message The debug message
     * @param array $data Additional data to log
     * @return void
     */
    protected function debug(string $message, array $data = []): void
    {
        try {
            $dataString = !empty($data) ? ' ' . json_encode($data) : '';
            $this->server->getLogger()->debug("[Sockeon HTTP/2] {$message}{$dataString}");
        } catch (Throwable $e) {
            $this->server->getLogger()->error("Failed to log message: " . $e->getMessage());
        }
    }
}
