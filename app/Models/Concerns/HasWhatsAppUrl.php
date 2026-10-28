<?php

namespace App\Models\Concerns;

/**
 * WhatsApp link is built from the stored E.164 number without the plus sign;
 * no link exists for a missing or invalid number (D-053).
 */
trait HasWhatsAppUrl
{
    public function whatsappUrl(): ?string
    {
        if (! is_string($this->phone) || ! preg_match('/^\+[1-9]\d{7,15}$/', $this->phone)) {
            return null;
        }

        return 'https://wa.me/'.ltrim($this->phone, '+');
    }
}
