<?php

declare(strict_types=1);

/**
 * @author Timo Förster <tfoerster@webfoersterei.de>
 * @date 02.06.2023
 */

namespace Webfoersterei\Wordpress\Plugin\UptainTracking;

interface TrackerInterface
{
    public function getCurrentTrackingData(): array;
}
