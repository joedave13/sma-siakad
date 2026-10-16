<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Gender $gender
 * @property Carbon|null $birth_date
 * @property StudentStatus $status
 */
#[Fillable([
    'user_id',
    'nisn',
    'nis',
    'full_name',
    'gender',
    'birth_date',
    'birth_place',
    'address',
    'status',
])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ClassEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class);
    }

    /**
     * @return HasManyThrough<Classroom, ClassEnrollment, $this>
     */
    public function classrooms(): HasManyThrough
    {
        return $this->hasManyThrough(Classroom::class, ClassEnrollment::class);
    }

    /**
     * @return HasMany<ReportCard, $this>
     */
    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /**
     * The student's classroom for the given academic year, if any.
     */
    public function classroomIn(int $academicYearId): ?Classroom
    {
        return $this->enrollments()
            ->where('academic_year_id', $academicYearId)
            ->with('classroom')
            ->first()?->classroom;
    }
}
