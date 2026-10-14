<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One cell of the plan step's price grid: per-posting price for tier × reach × postings. */
#[Fillable(['tier', 'reach', 'quantity', 'price_paise', 'mrp_paise'])]
class JobPlanPrice extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price_paise' => 'integer',
            'mrp_paise' => 'integer',
        ];
    }
}
