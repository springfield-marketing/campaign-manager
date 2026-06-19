<?php

namespace App\Enums;

enum RelationshipType: string
{
    case Owner = 'owner';
    case Resident = 'resident';
    case Tenant = 'tenant';
    case BuyerInterest = 'buyer_interest';
    case SellerInterest = 'seller_interest';
    case Investor = 'investor';
    case PastOwner = 'past_owner';
    case Unknown = 'unknown';
}
