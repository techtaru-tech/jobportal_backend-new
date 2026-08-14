<?php

namespace App\Models;

use App\Enums\InterviewType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['date', 'time', 'type', 'location_or_link', 'notes'])]
class Interview extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'type' => InterviewType::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
