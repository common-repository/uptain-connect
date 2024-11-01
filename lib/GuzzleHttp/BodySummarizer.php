<?php

namespace Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp;

use Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\Psr\Http\Message\MessageInterface;

final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;

    public function __construct(int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }

    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string
    {
        return $this->truncateAt === null
            ? \Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp\Psr7\Message::bodySummary($message)
            : \Webfoersterei\Wordpress\Plugin\UptainTracking\Dependencies\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}
