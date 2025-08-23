<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Tests\Fixtures;

use Crell\Serde\Attributes as Serde;
use PhoneBurner\Pinch\Component\PhoneNumber\AreaCode\AreaCode;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRoute;
use PhoneBurner\Pinch\Framework\Http\Api\Domain\SelfLinkRouteParameter;

/**
 * Eventually this is likely a projection, but for now it's something the
 * resolver can use to collect the result of a query.
 */
#[Serde\ClassSettings(includeFieldsByDefault: false)]
#[SelfLinkRoute('api.inventory.npa.resource', 'area_code')]
readonly class NpaInventory
{
    public function __construct(
        #[Serde\Field(serializedName: 'npa', scopes: ['api'])]
        #[SelfLinkRouteParameter('npa')]
        public AreaCode $area_code,
        #[Serde\Field(serializedName: 'available_count', scopes: ['api'])]
        public int $available,
    ) {
    }
}
