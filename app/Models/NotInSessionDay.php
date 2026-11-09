<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $school_number 0 means every school
 * @property CarbonImmutable $date
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['school_number', 'date', 'description'])]
class NotInSessionDay extends Model
{
    protected function casts(): array
    {
        return [
            'school_number' => 'integer',
            'date' => 'date',
        ];
    }

    /**
     * True when no kid has school on this date: either a district-wide day
     * off, or every school the kids attend is out.
     */
    public static function isDayOff(CarbonInterface $date): bool
    {
        $off = static::query()->whereDate('date', $date->toDateString())->pluck('school_number');

        if ($off->isEmpty()) {
            return false;
        }

        if ($off->contains(0)) {
            return true;
        }

        $schools = Section::query()->whereNotNull('school_number')->distinct()->pluck('school_number');

        return $schools->isNotEmpty() && $schools->diff($off)->isEmpty();
    }
}
