<?php

namespace App\Http\Controllers;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExerciseCatalogController extends Controller
{
    /**
     * Display the versioned exercise catalog.
     */
    public function index(Request $request, ExerciseCatalog $exerciseCatalog): View
    {
        $catalog = $exerciseCatalog->all();
        $search = $request->string('search')->trim()->toString();
        $category = $request->string('category')->trim()->toString();
        $muscle = $request->string('muscle')->trim()->toString();

        $exercises = Arr::get($catalog, 'exercises', []);
        if (! is_array($exercises)) {
            $exercises = [];
        }

        $exercises = array_values(array_filter(
            $exercises,
            function (mixed $exercise) use ($search, $category, $muscle): bool {
                if (! is_array($exercise)) {
                    return false;
                }

                $matchesSearch = $search === '' || Str::contains(
                    Str::lower(implode(' ', [
                        (string) ($exercise['id'] ?? ''),
                        (string) ($exercise['name'] ?? ''),
                        (string) ($exercise['description'] ?? ''),
                    ])),
                    Str::lower($search),
                );
                $matchesCategory = $category === '' || ($exercise['category'] ?? null) === $category;
                $muscles = array_merge($exercise['primaryMuscles'] ?? [], $exercise['secondaryMuscles'] ?? []);
                $matchesMuscle = $muscle === '' || in_array($muscle, $muscles, true);

                return $matchesSearch && $matchesCategory && $matchesMuscle;
            },
        ));
        usort($exercises, fn (array $left, array $right): int => ($left['name'] ?? '') <=> ($right['name'] ?? ''));

        return view('exercises.index', [
            'catalog' => $catalog,
            'exercises' => $exercises,
            'categories' => Arr::get($catalog, 'referenceData.exerciseCategories', []),
            'muscleGroups' => Arr::get($catalog, 'referenceData.muscleGroups', []),
            'filters' => compact('search', 'category', 'muscle'),
        ]);
    }
}
