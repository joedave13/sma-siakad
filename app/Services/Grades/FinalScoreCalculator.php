<?php

namespace App\Services\Grades;

use App\Enums\GradeComponent;

class FinalScoreCalculator
{
    /**
     * The weighted final score for one student's entries, rounded to an integer.
     *
     * Blank scores are not zero: any component without a score makes the
     * final score null (incomplete). Assignment and quiz entries are averaged.
     *
     * @param  array<int, array{component: GradeComponent|string, score: int|float|string|null}>  $entries
     */
    public function finalScore(array $entries): ?int
    {
        if ($this->missingComponents($entries) !== []) {
            return null;
        }

        $averages = $this->componentScores($entries);

        $weighted = 0.0;
        foreach (GradeComponent::cases() as $component) {
            $weighted += $averages[$component->value] * $component->weight();
        }

        return (int) round($weighted);
    }

    /**
     * The components that still have no score for this student.
     *
     * @param  array<int, array{component: GradeComponent|string, score: int|float|string|null}>  $entries
     * @return array<int, GradeComponent>
     */
    public function missingComponents(array $entries): array
    {
        $scores = $this->componentScores($entries);

        $missing = [];
        foreach (GradeComponent::cases() as $component) {
            if (! isset($scores[$component->value])) {
                $missing[] = $component;
            }
        }

        return $missing;
    }

    /**
     * Per-component effective score (assignment/quiz averaged), keyed by enum value.
     * Only components with at least one non-blank score are present.
     *
     * @param  array<int, array{component: GradeComponent|string, score: int|float|string|null}>  $entries
     * @return array<string, float>
     */
    private function componentScores(array $entries): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $component = $entry['component'] instanceof GradeComponent
                ? $entry['component']
                : GradeComponent::tryFrom((string) $entry['component']);

            if ($component === null || $entry['score'] === null || $entry['score'] === '') {
                continue;
            }

            $grouped[$component->value][] = (float) $entry['score'];
        }

        $scores = [];
        foreach ($grouped as $componentValue => $values) {
            $scores[$componentValue] = array_sum($values) / count($values);
        }

        return $scores;
    }
}
