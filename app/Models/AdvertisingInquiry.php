<?php

namespace App\Models;

use Database\Factories\AdvertisingInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An enquiry from the "Advertise on the Pond" card on Explore: someone who wants to promote something to the community.
 */
#[Fillable(['name', 'email', 'company', 'website', 'message'])]
class AdvertisingInquiry extends Model
{
    /** @use HasFactory<AdvertisingInquiryFactory> */
    use HasFactory;
}
