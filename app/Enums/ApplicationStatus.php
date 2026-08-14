<?php

namespace App\Enums;

/**
 * §1.8 — deliberately short. Paramedical and blue-collar hiring does not run a
 * corporate funnel: an employer looks at an application, decides whether the
 * person is worth talking to, then hires them or doesn't. Do not add stages.
 *
 * An interview is not a stage — it is an event attached to the application
 * (§9.4). Scheduling one sets `shortlisted` (unless already `selected`).
 *
 * Values are persisted by name, never by ordinal.
 */
enum ApplicationStatus: string
{
    case Applied = 'applied';
    case Shortlisted = 'shortlisted';
    case Selected = 'selected';
    case Rejected = 'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The linear progress the Track Application timeline draws. `rejected` is a
     * side-branch that can happen from any state, so it is not part of it.
     *
     * @return array<int, self>
     */
    public static function pipeline(): array
    {
        return [self::Applied, self::Shortlisted, self::Selected];
    }

    public function isPipelineStage(): bool
    {
        return $this !== self::Rejected;
    }

    /** 1-based position in the pipeline, or null for `rejected`. */
    public function pipelinePosition(): ?int
    {
        $index = array_search($this, self::pipeline(), true);

        return $index === false ? null : $index + 1;
    }

    /**
     * Position in the pipeline as a percentage — `shortlisted` is step 2 of 3,
     * so 67. Computed server-side so the app's progress bar can never disagree
     * with the status text.
     */
    public function progressPercent(): int
    {
        $position = $this->pipelinePosition();

        if ($position === null) {
            return 0;
        }

        return (int) round($position / count(self::pipeline()) * 100);
    }
}
