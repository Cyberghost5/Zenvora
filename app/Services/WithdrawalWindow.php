<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Answers "can money leave the platform right now?" from the admin's schedule.
 *
 * The admin configures which weekdays withdrawals are permitted plus an opening
 * and closing time. This class is the single place that decides -- the request
 * form and the service layer both consult it, so a user cannot bypass the
 * schedule by POSTing directly.
 */
class WithdrawalWindow
{
    private const DAY_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function timezone(): string
    {
        return $this->settings->string('withdrawal_timezone', 'Africa/Lagos');
    }

    public function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    /** @return array<int, int> ISO weekdays, 1 = Monday. */
    public function permittedDays(): array
    {
        $days = array_map('intval', $this->settings->array('withdrawal_days', [1, 2, 3, 4, 5]));
        $days = array_values(array_unique(array_filter($days, fn (int $d) => $d >= 1 && $d <= 7)));
        sort($days);

        return $days;
    }

    public function opensAt(): string
    {
        return $this->settings->string('withdrawal_opens_at', '09:00');
    }

    public function closesAt(): string
    {
        return $this->settings->string('withdrawal_closes_at', '17:00');
    }

    public function withdrawalsEnabled(): bool
    {
        return $this->settings->boolean('withdrawal_enabled', true);
    }

    /**
     * Whether the window is open at the given moment.
     */
    public function isOpen(?Carbon $at = null): bool
    {
        if (! $this->withdrawalsEnabled()) {
            return false;
        }

        $at = $at ? $at->copy()->setTimezone($this->timezone()) : $this->now();

        if (! in_array($at->isoWeekday(), $this->permittedDays(), true)) {
            return false;
        }

        return $this->isWithinHours($at);
    }

    /**
     * Time-of-day check, tolerant of a window that wraps past midnight
     * (e.g. 22:00 to 04:00).
     */
    private function isWithinHours(Carbon $at): bool
    {
        $open = $this->minutesFrom($this->opensAt());
        $close = $this->minutesFrom($this->closesAt());
        $current = $at->hour * 60 + $at->minute;

        // An identical open and close time is treated as all day, which is the
        // least surprising reading of "00:00 to 00:00".
        if ($open === $close) {
            return true;
        }

        return $open < $close
            ? $current >= $open && $current < $close
            : $current >= $open || $current < $close;
    }

    private function minutesFrom(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time, 2), 2, '0');

        return ((int) $hours) * 60 + ((int) $minutes);
    }

    /**
     * Why the window is shut, phrased for the user. Null when it is open.
     */
    public function closedReason(?Carbon $at = null): ?string
    {
        if ($this->isOpen($at)) {
            return null;
        }

        if (! $this->withdrawalsEnabled()) {
            return 'Withdrawals are temporarily disabled by the administrator.';
        }

        $at = $at ? $at->copy()->setTimezone($this->timezone()) : $this->now();

        if (! in_array($at->isoWeekday(), $this->permittedDays(), true)) {
            return sprintf(
                'Withdrawals are only processed on %s. The next window opens %s.',
                $this->permittedDaysLabel(),
                $this->nextOpeningLabel($at),
            );
        }

        return sprintf(
            'Withdrawals are open between %s and %s (%s). The next window opens %s.',
            $this->opensAt(),
            $this->closesAt(),
            $this->timezoneLabel(),
            $this->nextOpeningLabel($at),
        );
    }

    /**
     * The next moment the window opens, searching forward up to a fortnight.
     */
    public function nextOpening(?Carbon $from = null): ?Carbon
    {
        if (! $this->withdrawalsEnabled() || $this->permittedDays() === []) {
            return null;
        }

        $from = $from ? $from->copy()->setTimezone($this->timezone()) : $this->now();
        [$openHour, $openMinute] = array_pad(explode(':', $this->opensAt(), 2), 2, '0');

        for ($offset = 0; $offset <= 14; $offset++) {
            $candidate = $from->copy()->addDays($offset)
                ->setTime((int) $openHour, (int) $openMinute);

            if (! in_array($candidate->isoWeekday(), $this->permittedDays(), true)) {
                continue;
            }

            // Today's opening may already have passed.
            if ($candidate->lessThanOrEqualTo($from)) {
                if ($this->isOpen($from)) {
                    return $from;
                }

                continue;
            }

            return $candidate;
        }

        return null;
    }

    public function nextOpeningLabel(?Carbon $from = null): string
    {
        $next = $this->nextOpening($from);

        if (! $next) {
            return 'once an administrator re-enables withdrawals';
        }

        return $next->isToday()
            ? 'today at '.$next->format('H:i')
            : $next->calendar($from ?? $this->now());
    }

    public function permittedDaysLabel(): string
    {
        $days = $this->permittedDays();

        if ($days === []) {
            return 'no days';
        }

        if (count($days) === 7) {
            return 'every day';
        }

        $names = array_map(fn (int $d) => self::DAY_NAMES[$d], $days);

        if (count($names) === 1) {
            return $names[0].'s';
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }

    public function timezoneLabel(): string
    {
        return str_replace('_', ' ', $this->timezone());
    }

    /**
     * A one-line summary for the dashboard.
     */
    public function summary(): string
    {
        if (! $this->withdrawalsEnabled()) {
            return 'Withdrawals are currently disabled.';
        }

        return sprintf(
            '%s, %s to %s (%s)',
            ucfirst($this->permittedDaysLabel()),
            $this->opensAt(),
            $this->closesAt(),
            $this->timezoneLabel(),
        );
    }

    /** @return array<int, string> */
    public static function dayNames(): array
    {
        return self::DAY_NAMES;
    }
}
