<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'abbreviation',
        'address',
        'website',
        'email',
        'hotline',
        'type',
        'has_faculties',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_faculties' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
