<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ps_id', 'name', 'abbreviation', 'starts_on', 'ends_on'])]
class Term extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** @return HasMany<ReportingTerm, $this> */
    public function reportingTerms(): HasMany
    {
        return $this->hasMany(ReportingTerm::class);
    }
}
