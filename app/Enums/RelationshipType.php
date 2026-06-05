<?php

namespace App\Enums;

enum RelationshipType: string
{
    case Owner          = 'owner';
    case Resident       = 'resident';
    case Tenant         = 'tenant';
    case BuyerInterest  = 'buyer_interest';
    case SellerInterest = 'seller_interest';
    case Investor       = 'investor';
    case PastOwner      = 'past_owner';
    case Unknown        = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Owner          => 'Owner',
            self::Resident       => 'Resident',
            self::Tenant         => 'Tenant',
            self::BuyerInterest  => 'Buyer Interest',
            self::SellerInterest => 'Seller Interest',
            self::Investor       => 'Investor',
            self::PastOwner      => 'Past Owner',
            self::Unknown        => 'Unknown',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value',
        );
    }
}
