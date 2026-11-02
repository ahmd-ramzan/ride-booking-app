<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlaceController extends Controller
{
    /**
     * Known places in the service area, optionally filtered by `search`.
     * Stand-in for a geocoding/autocomplete provider.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $search = Str::lower(trim($request->string('search')->limit(100, '')->toString()));

        /** @var list<array{name: string, latitude: float, longitude: float}> $places */
        $places = config('rides.places');

        $matches = collect($places)
            ->filter(fn (array $place): bool => $search === '' || Str::contains(Str::lower($place['name']), $search))
            ->values();

        return response()->json(['data' => $matches]);
    }
}
