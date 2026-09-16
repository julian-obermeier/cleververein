<?php

namespace App\Services\Finance;

use App\Models\Household;
use App\Models\Member;

class FinanceRecipientService
{
    public function snapshot(?Member $member = null, ?Household $household = null): array
    {
        if ($household) {
            $household->loadMissing('members.person');
            $primary = $household->members->first(fn (Member $member) => (bool) $member->pivot?->is_primary_contact)
                ?? $household->members->first();
            $contact = $household->contact_data ?? [];
            $personContact = $primary?->person?->contact_data ?? [];

            return [
                'type' => 'household',
                'name' => $household->name,
                'member_number' => $primary?->member_number,
                'contact_name' => $primary?->person?->display_name,
                'email' => $contact['email'] ?? $primary?->person?->email,
                'street' => $contact['street'] ?? $personContact['street'] ?? null,
                'postal_code' => $contact['postal_code'] ?? $personContact['postal_code'] ?? null,
                'city' => $contact['city'] ?? $personContact['city'] ?? null,
                'country' => $contact['country'] ?? $personContact['country'] ?? 'DE',
            ];
        }

        $member?->loadMissing('person');
        $contact = $member?->person?->contact_data ?? [];

        return [
            'type' => 'member',
            'name' => $member?->person?->display_name ?? 'Rechnungsempfänger',
            'member_number' => $member?->member_number,
            'contact_name' => $member?->person?->display_name,
            'email' => $member?->person?->email,
            'street' => $contact['street'] ?? null,
            'postal_code' => $contact['postal_code'] ?? null,
            'city' => $contact['city'] ?? null,
            'country' => $contact['country'] ?? 'DE',
        ];
    }
}
