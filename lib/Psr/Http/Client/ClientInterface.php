<?php

namespace Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Client;

use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Message\RequestInterface;
use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
