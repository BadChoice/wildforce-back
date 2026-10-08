<?php

namespace App\Services\Nutrition;

use App\Ai\Agents\Nutrition\NutritionPlanGeneratorAgent;
use App\Enums\MealType;
use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Services\Nutrition\Progression\EnergyExpenditureEstimate;
use App\Services\Nutrition\Progression\NutritionProgressionAnalysis;
use App\Services\Nutrition\Progression\NutritionProgressionAnalyzer;
use Carbon\CarbonInterface;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final class NutritionPlanAIGenerator
{
    /**
     * @param  list<array{date: string, active_calories: float, basal_calories: float|null}>  $dailyEnergy  Apple Health days reported by the client, if any
     */
    public function generate(User $user, array $dailyEnergy = []): NutritionPlan
    {
        $analysis = $this->analysis($user, $dailyEnergy);
        $response = $this->agent($analysis->wantsMealSuggestions)->prompt($this->prompt($user, $analysis));

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The nutrition plan generator did not return structured data.');
        }

        return $this->planFromResponse($user, $analysis, $response->toArray());
    }

    /**
     * Generate and persist a nutrition plan with its days and meals.
     *
     * @param  list<array{date: string, active_calories: float, basal_calories: float|null}>  $dailyEnergy  Apple Health days reported by the client, if any
     */
    public function generateAndPersist(User $user, array $dailyEnergy = []): NutritionPlan
    {
        $plan = $this->generate($user, $dailyEnergy);

        return DB::transaction(function () use ($plan): NutritionPlan {
            $plan->save();

            foreach ($plan->days as $day) {
                $plan->days()->save($day);

                foreach ($day->meals as $meal) {
                    $day->meals()->save($meal);
                }
            }

            return $plan->load('days.meals');
        });
    }

    /** @return array{instructions: string, prompt: string, schema: string} */
    public function preview(User $user): array
    {
        $analysis = $this->analysis($user);
        $agent = $this->agent($analysis->wantsMealSuggestions);
        $schema = json_encode(
            (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory)))->toSchema(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (! is_string($schema)) {
            throw new RuntimeException('The nutrition plan response schema could not be encoded.');
        }

        return ['instructions' => $agent->instructions(), 'prompt' => $this->prompt($user, $analysis), 'schema' => $schema];
    }

    private function agent(bool $wantsMealSuggestions): NutritionPlanGeneratorAgent
    {
        return new NutritionPlanGeneratorAgent($wantsMealSuggestions);
    }

    /**
     * @param  list<array{date: string, active_calories: float, basal_calories: float|null}>  $dailyEnergy
     */
    private function analysis(User $user, array $dailyEnergy = []): NutritionProgressionAnalysis
    {
        return (new NutritionProgressionAnalyzer($user, $dailyEnergy))->analyze();
    }

    private function prompt(User $user, NutritionProgressionAnalysis $analysis): string
    {
        $profile = $user->nutritionProfile;
        $preferences = $user->trainingPreferences;
        $profileNotes = $profile->notes ?? 'None';
        $sourceWorkoutPlanName = $analysis->sourceWorkoutPlan?->name ?? 'None';
        $workoutSchedule = collect($analysis->days)->map(function (array $day): string {
            $workoutDay = $day['workoutDay'];

            return $workoutDay === null
                ? '- '.$day['weekday'].' ('.$day['date']->toDateString().'): no planned workout'
                : '- '.$day['weekday'].' ('.$day['date']->toDateString().'): '.$workoutDay->title.', focus '.$workoutDay->focus->value.', estimated duration '.($workoutDay->estimated_duration_minutes ?? 45).' min';
        })->implode("\n");
        $targets = collect($analysis->days)->map(fn (array $day): string => '- '.$day['weekday'].' | '.$day['date']->toDateString().' | '.$this->workoutLabel($day['workoutDay']).' | dayType: '.$day['dayType']->value.' | demand: '.$day['energyDemand']->value.' | calories: '.$day['calories'].' | protein: '.$day['protein'].'g | carbs: '.$day['carbs'].'g | fat: '.$day['fat'].'g')->implode("\n");

        return <<<PROMPT
## Planning objective
- Create a seven-day nutrition plan starting on {$analysis->startsOn->toDateString()}.
- Use the exact daily calorie and macro targets provided below.
- Write all user-facing plan notes, day notes, meal titles, and meal guidance in {$user->language}.

## Client context
- Goal: {$analysis->goal}
- Body composition phase: {$analysis->bodyCompositionPhase}
- Training level: {$preferences?->general_training_level}
- Lifestyle: {$preferences?->lifestyle}
- Maintenance energy: {$this->energyExpenditure($analysis->energyExpenditure)}
- Age: {$user->birth_date->age}
- Height: {$user->height} cm
- Weight: {$user->weight} kg
- Gender: {$user->gender}
- Preferred language: {$user->language}

## Nutrition preferences
- Dietary style: {$profile->dietary_style->value}
- Meals per day preference: {$profile->meals_per_day_preference}
- Preferred eating window: {$this->eatingWindow($profile->preferred_eating_window_start_hour, $profile->preferred_eating_window_end_hour)}
- Cooking effort: {$profile->cooking_effort->value}
- Budget sensitivity: {$profile->budget_sensitivity->value}
- Preferred protein sources: {$this->list($profile->preferred_protein_sources)}
- Dislikes: {$this->list($profile->dislikes)}
- Excluded foods: {$this->list($profile->excluded_foods)}
- Allergies / intolerances: {$this->list($profile->allergies_and_intolerances)}
- Wants meal suggestions: {$this->yesNo($analysis->wantsMealSuggestions)}
- Notes: {$profileNotes}

## Upcoming training schedule
- Source workout plan: {$sourceWorkoutPlanName}
{$workoutSchedule}

## Recent body metrics
{$this->bodyMetrics($user)}

## Deterministic daily targets
These values are computed by the app and must be echoed exactly in the JSON output.
{$targets}

Return exactly seven days in chronological order. The `dailyCalorieAverage` must be the arithmetic average of the supplied daily calories. Keep planned-workout fields aligned with the training schedule. When meal suggestions are disabled, return an empty `meals` array for every day.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function planFromResponse(User $user, NutritionProgressionAnalysis $analysis, array $response): NutritionPlan
    {
        $plan = new NutritionPlan;
        $plan->forceFill([
            'user_id' => $user->id,
            'source_workout_plan_id' => $analysis->sourceWorkoutPlan?->id,
            'starts_on' => $analysis->startsOn,
            'goal' => $analysis->goal,
            'body_composition_phase' => $analysis->bodyCompositionPhase,
            'daily_calorie_average' => collect($analysis->days)->avg('calories'),
            'notes' => $response['notes'] ?? null,
        ]);
        $plan->setRelation('user', $user);
        $plan->setRelation('sourceWorkoutPlan', $analysis->sourceWorkoutPlan);
        $plan->setRelation('days', collect($analysis->days)->map(fn (array $day, int $index): NutritionDay => $this->dayFromResponse($plan, $day, $this->responseDay($response['days'] ?? [], $day['date'], $index), $analysis->wantsMealSuggestions)));

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $response
     */
    private function dayFromResponse(NutritionPlan $plan, array $context, ?array $response, bool $wantsMealSuggestions): NutritionDay
    {
        $day = new NutritionDay;
        $day->forceFill([
            'date' => $context['date'], 'weekday' => $context['weekday'], 'day_type' => $context['dayType'],
            'target_calories' => $context['calories'], 'target_protein_grams' => $context['protein'], 'target_carbs_grams' => $context['carbs'], 'target_fat_grams' => $context['fat'],
            'planned_workout_title' => $context['workoutDay']?->title, 'planned_workout_focus' => $context['workoutDay']?->focus?->value, 'energy_demand' => $context['energyDemand'],
            'notes' => $response['notes'] ?? null, 'pre_workout_guidance' => $response['preWorkoutGuidance'] ?? null, 'post_workout_guidance' => $response['postWorkoutGuidance'] ?? null,
        ]);
        $day->setRelation('plan', $plan);
        $day->setRelation('meals', $wantsMealSuggestions ? collect($response['meals'] ?? [])->values()->map(fn (array $meal, int $index): NutritionMeal => $this->mealFromResponse($day, $meal, $index)) : collect());

        return $day;
    }

    /** @param array<string, mixed> $response */
    private function mealFromResponse(NutritionDay $day, array $response, int $index): NutritionMeal
    {
        $macros = $response['targetMacros'] ?? [];
        $meal = new NutritionMeal;
        $meal->forceFill([
            'title' => $response['title'], 'order_index' => $index, 'meal_type' => MealType::from($response['mealType']),
            'target_calories' => $macros['calories'], 'target_protein_grams' => $macros['protein'], 'target_carbs_grams' => $macros['carbs'], 'target_fat_grams' => $macros['fat'],
            'guidance' => $response['guidance'] ?? null, 'example_foods' => $response['exampleFoods'] ?? [],
        ]);
        $meal->setRelation('day', $day);

        return $meal;
    }

    /** @param list<array<string, mixed>> $days */
    private function responseDay(array $days, CarbonInterface $date, int $index): ?array
    {
        return collect($days)->first(fn (array $day): bool => ($day['date'] ?? null) === $date->toDateString()) ?? ($days[$index] ?? null);
    }

    private function bodyMetrics(User $user): string
    {
        $metrics = $user->bodyMetrics->sortByDesc('recorded_at')->take(12);

        return $metrics->isEmpty() ? '- No logged body metrics beyond the current user profile.' : $metrics->map(fn ($metric): string => '- '.$metric->type.': '.$metric->value.' on '.$metric->recorded_at->toDateString())->implode("\n");
    }

    private function energyExpenditure(EnergyExpenditureEstimate $estimate): string
    {
        $healthDays = $estimate->observedDays.' of '.$estimate->consideredDays.' days';

        return $estimate->averageDailyCalories.' kcal/day ('.match ($estimate->source) {
            'healthKit' => 'measured by Apple Health over '.$healthDays,
            'blended' => 'Apple Health over '.$healthDays.' blended with the formula estimate',
            'fallback' => 'formula estimate',
        }.')';
    }

    private function workoutLabel(?WorkoutDay $workoutDay): string
    {
        return $workoutDay?->title ?? 'Rest';
    }

    /** @param list<string>|null $values */
    private function list(?array $values): string
    {
        return $values === [] || $values === null ? 'None' : implode(', ', $values);
    }

    private function eatingWindow(?int $start, ?int $end): string
    {
        return $start === null || $end === null ? 'Flexible' : sprintf('%02d:00-%02d:00', $start, $end);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
