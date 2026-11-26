<?php

namespace App\Models;

use Database\Factories\AdvertisingInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An enquiry from the "Advertise on the Pond" card on Explore: someone who wants to promote something to the community.
 * An admin marks it handled once they have replied.
 *
 * @property Carbon|null $handled_at
 */
#[Fillable(['name', 'email', 'company', 'website', 'message'])]
class AdvertisingInquiry extends Model
{
    /** @use HasFactory<AdvertisingInquiryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }
}
