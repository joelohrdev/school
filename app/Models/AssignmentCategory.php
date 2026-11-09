<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ps_id', 'name', 'abbreviation', 'description'])]
class AssignmentCategory extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
        ];
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
