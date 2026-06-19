<?php

namespace App\Modules\WhatsApp\Enums;

enum WhatsAppImportType: string
{
    // RETIRED (Phase 3): the WhatsApp raw-contacts importer was removed — raw contacts come in via
    // the single Contacts → Imports path. The case is kept so the few historical raw_contacts
    // import records still cast/display; no new ones are created. See docs/data-rules/imports.md.
    case RawContacts = 'raw_contacts';
    case CampaignResults = 'campaign_results';
    case Unsubscribers = 'unsubscribers';
}
