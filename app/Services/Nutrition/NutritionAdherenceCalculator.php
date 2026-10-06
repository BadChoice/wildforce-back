<?php

namespace App\Services\Nutrition;

use App\Enums\NutritionAdherenceStatus;
use App\Models\NutritionDay;
use App\Models\NutritionLogEntry;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Services\Nutrition\Adherence\NutritionAdherenceSummary;
use App\Services\Nutrition\Adherence\NutritionDayAdherence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Compares what a user logged against their nutrition plan targets, day by day in the user's timezone.
 */
final class NutritionAdherenceCalculator
{
    /**
     * Allowed deviation from the calorie target, as a fraction, for a day to count as on target.
     */
    public const float CalorieTolerance = 0.10;

    /**
     * Minimum share of the protein target that must be logged for a day to count as on target.
     */
    public const float MinimumProteinRatio = 0.90;

    /**
     * Number of complete days covered by the summary.
     */
    public const int SummaryPeriodDays = 7;

    public function today(User $user): CarbonImmutable
    {
        return CarbonImmutable::now($user->preferredTimezone())->startOfDay();
    }

    /**
     * Compare each local day in the range with its targets. When a plan is given, only that plan's targets are used.
     *
     * @return list<NutritionDayAdherence>
     */
    public function days(User $user, CarbonInterface $from, CarbonInterface $to, ?NutritionPlan $plan = null): array
    {
        $timezone = $user->preferredTimezone();
        $from = CarbonImmutable::parse($from->toDateString(), $timezone);
        $to = CarbonImmutable::parse($to->toDateString(), $timezone);
        $today = $this->today($user);
        $planDays = $this->planDaysBetween($user, $from, $to, $plan);
        $totals = $this->loggedTotalsBetween($user, $from, $to);

        return collect(CarbonPeriod::create($from, $to))
            ->map(function (CarbonInterface $date) use ($planDays, $totals, $today, $timezone): NutritionDayAdherence {
                $date = CarbonImmutable::parse($date->toDateString(), $timezone);
                $key = $date->toDateString();
                $planDay = $planDays->get($key);
                $total = $totals[$key] ?? ['entries' => 0, 'calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];

                return new NutritionDayAdherence(
                    date: $date,
                    planDay: $planDay,
                    entryCount: $total['entries'],
                    calories: $total['calories'],
                    proteinGrams: $total['protein'],
                    carbsGrams: $total['carbs'],
                    fatGrams: $total['fat'],
                    status: $this->status($date, $today, $planDay, $total),
                    isToday: $date->equalTo($today),
                );
            })
            ->values()
            ->all();
    }

    /**
     * Summarise the complete days before today, plus the user's plan and logging state.
     */
    public function summary(User $user, int $periodDays = self::SummaryPeriodDays): NutritionAdherenceSummary
    {
        $today = $this->today($user);
        $lastLoggedAt = $user->nutritionLogEntries()->max('logged_at');

        return new NutritionAdherenceSummary(
            today: $today,
            days: $this->days($user, $today->subDays($periodDays), $today->subDay()),
            lastLoggedAt: $lastLoggedAt === null ? null : CarbonImmutable::parse($lastLoggedAt, config('app.timezone'))->setTimezone($user->preferredTimezone()),
            activePlan: $user->nutritionPlans()
                ->whereDate('starts_on', '<=', $today->toDateString())
                ->whereDate('starts_on', '>=', $today->subDays(6)->toDateString())
                ->latest()
                ->first(),
            latestPlan: $user->nutritionPlans()
                ->whereDate('starts_on', '<=', $today->toDateString())
                ->orderByDesc('starts_on')
                ->latest()
                ->first(),
        );
    }

    /**
     * The entries the user logged on a local calendar date, with their items and photo.
     *
     * @return Collection<int, NutritionLogEntry>
     */
    public function entriesOn(User $user, CarbonInterface $date): Collection
    {
        $date = CarbonImmutable::parse($date->toDateString(), $user->preferredTimezone());

        return $user->nutritionLogEntries()
            ->whereBetween('logged_at', $this->storageRange($date, $date))
            ->with([
                'items' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                'media',
            ])
            ->orderBy('logged_at')
            ->get();
    }

    /**
     * Count the logged days in the summary period and the last log time for several users in two queries.
     *
     * @param  iterable<User>  $users
     * @return array<string, array{loggedDays: int, periodDays: int, lastLoggedAt: CarbonImmutable|null}>
     */
    public function recentLogging(iterable $users, int $periodDays = self::SummaryPeriodDays): array
    {
        $users = collect($users)->keyBy(fn (User $user): string => $user->getKey());

        if ($users->isEmpty()) {
            return [];
        }

        $loggedAtByUser = NutritionLogEntry::query()
            ->whereIn('user_id', $users->keys())
            ->where('logged_at', '>=', now()->subDays($periodDays + 2)->startOfDay())
            ->get(['user_id', 'logged_at'])
            ->groupBy('user_id');
        $lastLoggedAtByUser = NutritionLogEntry::query()
            ->whereIn('user_id', $users->keys())
            ->groupBy('user_id')
            ->selectRaw('user_id, max(logged_at) as last_logged_at')
            ->pluck('last_logged_at', 'user_id');

        return $users->map(function (User $user, string $userId) use ($loggedAtByUser, $lastLoggedAtByUser, $periodDays): array {
            $timezone = $user->preferredTimezone();
            $today = $this->today($user);
            $from = $today->subDays($periodDays)->toDateString();
            $to = $today->subDay()->toDateString();
            $loggedDays = collect($loggedAtByUser->get($userId, []))
                ->map(fn (NutritionLogEntry $entry): string => $entry->logged_at->setTimezone($timezone)->toDateString())
                ->filter(fn (string $date): bool => $date >= $from && $date <= $to)
                ->unique()
                ->count();
            $lastLoggedAt = $lastLoggedAtByUser->get($userId);

            return [
                'loggedDays' => $loggedDays,
                'periodDays' => $periodDays,
                'lastLoggedAt' => $lastLoggedAt === null ? null : CarbonImmutable::parse($lastLoggedAt, config('app.timezone'))->setTimezone($timezone),
            ];
        })->all();
    }

    /**
     * The plan day for each date, preferring the most recently created plan when plans overlap.
     *
     * @return SupportCollection<string, NutritionDay>
     */
    private function planDaysBetween(User $user, CarbonImmutable $from, CarbonImmutable $to, ?NutritionPlan $plan): SupportCollection
    {
        return NutritionDay::query()
            ->when(
                $plan === null,
                fn (Builder $query): Builder => $query->whereHas('plan', fn (Builder $query): Builder => $query->whereBelongsTo($user)),
                fn (Builder $query): Builder => $query->whereBelongsTo($plan, 'plan'),
            )
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['plan' => fn (BelongsTo $query): BelongsTo => $query->select(['id', 'created_at'])])
            ->get()
            ->sortBy(fn (NutritionDay $day): string => $day->plan->created_at?->toISOString() ?? '')
            ->keyBy(fn (NutritionDay $day): string => $day->date->toDateString());
    }

    /**
     * @return array<string, array{entries: int, calories: float, protein: float, carbs: float, fat: float}>
     */
    private function loggedTotalsBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $timezone = $user->preferredTimezone();
        $totals = [];

        $user->nutritionLogEntries()
            ->whereBetween('logged_at', $this->storageRange($from, $to))
            ->select(['id', 'user_id', 'logged_at'])
            ->withSum('items as total_calories', 'calories')
            ->withSum('items as total_protein_grams', 'protein_grams')
            ->withSum('items as total_carbs_grams', 'carbs_grams')
            ->withSum('items as total_fat_grams', 'fat_grams')
            ->get()
            ->each(function (NutritionLogEntry $entry) use (&$totals, $timezone): void {
                $key = $entry->logged_at->setTimezone($timezone)->toDateString();
                $totals[$key] ??= ['entries' => 0, 'calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];
                $totals[$key]['entries']++;
                $totals[$key]['calories'] += (float) $entry->total_calories;
                $totals[$key]['protein'] += (float) $entry->total_protein_grams;
                $totals[$key]['carbs'] += (float) $entry->total_carbs_grams;
                $totals[$key]['fat'] += (float) $entry->total_fat_grams;
            });

        return $totals;
    }

    /**
     * Convert a range of local calendar days into the stored timestamp range.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function storageRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $storageTimezone = (string) config('app.timezone');

        return [
            $from->startOfDay()->setTimezone($storageTimezone),
            $to->endOfDay()->setTimezone($storageTimezone),
        ];
    }

    /**
     * @param  array{entries: int, calories: float, protein: float, carbs: float, fat: float}  $total
     */
    private function status(CarbonImmutable $date, CarbonImmutable $today, ?NutritionDay $planDay, array $total): NutritionAdherenceStatus
    {
        if ($date->greaterThan($today)) {
            return NutritionAdherenceStatus::Upcoming;
        }

        $targetCalories = (float) $planDay?->target_calories;

        if ($targetCalories <= 0) {
            return NutritionAdherenceStatus::NoPlan;
        }

        $isToday = $date->equalTo($today);

        if ($total['entries'] === 0) {
            return $isToday ? NutritionAdherenceStatus::InProgress : NutritionAdherenceStatus::NotLogged;
        }

        $calorieRatio = $total['calories'] / $targetCalories;
        $targetProtein = (float) $planDay->target_protein_grams;
        $reachesProtein = $targetProtein <= 0 || $total['protein'] >= $targetProtein * self::MinimumProteinRatio;

        return match (true) {
            $calorieRatio > 1 + self::CalorieTolerance => NutritionAdherenceStatus::Over,
            $calorieRatio >= 1 - self::CalorieTolerance && $reachesProtein => NutritionAdherenceStatus::OnTarget,
            $isToday => NutritionAdherenceStatus::InProgress,
            $calorieRatio < 1 - self::CalorieTolerance => NutritionAdherenceStatus::Under,
            default => NutritionAdherenceStatus::LowProtein,
        };
    }
}
