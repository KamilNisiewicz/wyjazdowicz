<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class NominatimGeocoder
{
    /**
     * @return array<int, array{display_name: string, lat: float, lon: float}>
     */
    public function search(string $query): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.nominatim.user_agent'),
            ])
                ->timeout(5)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 5,
                ]);
        } catch (Throwable) {
            return [];
        }

        if ($response->failed()) {
            return [];
        }

        return collect($response->json())
            ->map(fn (array $result) => [
                'display_name' => $result['display_name'],
                'lat' => (float) $result['lat'],
                'lon' => (float) $result['lon'],
            ])
            ->all();
    }
}
