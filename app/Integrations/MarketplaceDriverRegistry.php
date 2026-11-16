<?php

declare(strict_types=1);

namespace App\Integrations;

final class MarketplaceDriverRegistry
{
    /** @return list<MarketplaceDriver> */
    public static function configured(): array
    {
        return [
            new AmazonCreatorsApiDriver(),
            new AuthorizedJsonFeedDriver(),
        ];
    }
}
