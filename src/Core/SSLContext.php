<?php
/**
 * SSLContext class
 * 
 * Manages SSL configuration for secure WebSocket and HTTP connections
 * Configures SSL/TLS context options for secure connections
 * 
 * @package     Xentixar\Socklet
 * @author      Xentixar
 * @copyright   Copyright (c) 2025
 */

namespace Xentixar\Socklet\Core;

class SSLContext
{
    /**
     * SSL context options
     * @var array
     */
    protected array $options = [];

    /**
     * Constructor
     * 
     * @param string|null $certPath      Path to SSL certificate file
     * @param string|null $keyPath       Path to SSL private key file
     * @param string|null $password      Optional passphrase for the private key
     * @param array       $extraOptions  Additional SSL context options
     */
    public function __construct(
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $password = null,
        array $extraOptions = []
    ) {
        $this->options = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        if ($certPath && $keyPath) {
            $this->options['ssl']['local_cert'] = $certPath;
            $this->options['ssl']['local_pk'] = $keyPath;
            
            if ($password) {
                $this->options['ssl']['passphrase'] = $password;
            }
        }

        if (!empty($extraOptions)) {
            $this->options['ssl'] = array_merge($this->options['ssl'], $extraOptions);
        }
    }

    /**
     * Get the stream context with SSL options
     * 
     * @return resource  The created stream context resource
     */
    public function getContext()
    {
        return stream_context_create($this->options);
    }
}
