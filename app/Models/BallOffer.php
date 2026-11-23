<?php

namespace App\Models;

use Database\Factories\BallOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An enquiry sent from "The Deep End": a visitor asking to make an offer on one of the very expensive balls.
 */
#[Fillable(['name', 'email', 'message'])]
class BallOffer extends Model
{
    /** @use HasFactory<BallOfferFactory> */
    use HasFactory;
}
