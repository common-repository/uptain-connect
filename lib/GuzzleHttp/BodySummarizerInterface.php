<?php

namespace Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp;

use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
