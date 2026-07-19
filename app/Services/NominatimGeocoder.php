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

            if ($response->failed()) {
                return [];
            }

            return collect($response->json())
                ->filter(fn ($result) => is_array($result)
                    && isset($result['display_name'], $result['lat'], $result['lon']))
                ->map(fn (array $result) => [
                    'display_name' => $result['display_name'],
                    'lat' => (float) $result['lat'],
                    'lon' => (float) $result['lon'],
                ])
                // Nominatim może zwrócić kilka obiektów OSM (węzeł, granica administracyjna) dla
                // tego samego miasta z identycznym display_name, ale lekko różnymi współrzędnymi —
                // nieodróżnialne w UI, więc bierzemy pierwszy (najbardziej trafny) wynik.
                ->unique('display_name')
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
