<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ps_id
 * @property string|null $name
 * @property string|null $abbreviation
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
