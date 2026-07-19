<?php

namespace Tests\Unit;

use App\Services\NominatimGeocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NominatimGeocoderTest extends TestCase
{
    public function test_search_maps_nominatim_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Warszawa, Polska', 'lat' => '52.2319581', 'lon' => '21.0067249'],
                ['display_name' => 'Warszawa, Ohio, USA', 'lat' => '39.5', 'lon' => '-84.0'],
            ], 200),
        ]);

        $results = (new NominatimGeocoder)->search('Warszawa');

        $this->assertSame([
            ['display_name' => 'Warszawa, Polska', 'lat' => 52.2319581, 'lon' => 21.0067249],
            ['display_name' => 'Warszawa, Ohio, USA', 'lat' => 39.5, 'lon' => -84.0],
        ], $results);
    }

    public function test_search_deduplicates_results_with_identical_display_name(): void
    {
        // Nominatim zwraca to realnie dla niektórych miast (np. "Zabrze") — kilka obiektów
        // OSM z tym samym display_name, ale nieznacznie innymi współrzędnymi.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Zabrze, województwo śląskie, Polska', 'lat' => '50.3142806', 'lon' => '18.7815763'],
                ['display_name' => 'Zabrze, województwo śląskie, Polska', 'lat' => '50.3086154', 'lon' => '18.7863749'],
            ], 200),
        ]);

        $results = (new NominatimGeocoder)->search('Zabrze');

        $this->assertSame([
            ['display_name' => 'Zabrze, województwo śląskie, Polska', 'lat' => 50.3142806, 'lon' => 18.7815763],
        ], $results);
    }

    public function test_search_skips_malformed_records_instead_of_throwing(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Warszawa, Polska', 'lat' => '52.23', 'lon' => '21.01'],
                ['display_name' => 'Rekord bez współrzędnych'],
            ], 200),
        ]);

        $results = (new NominatimGeocoder)->search('Warszawa');

        $this->assertSame([
            ['display_name' => 'Warszawa, Polska', 'lat' => 52.23, 'lon' => 21.01],
        ], $results);
    }

    public function test_search_returns_empty_array_when_no_results(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $results = (new NominatimGeocoder)->search('asdkjaslkdjaslkdj');

        $this->assertSame([], $results);
    }

    public function test_search_returns_empty_array_on_server_error(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(null, 500),
        ]);

        $results = (new NominatimGeocoder)->search('Warszawa');

        $this->assertSame([], $results);
    }

    public function test_search_returns_empty_array_on_connection_failure(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => fn () => throw new ConnectionException('timed out'),
        ]);

        $results = (new NominatimGeocoder)->search('Warszawa');

        $this->assertSame([], $results);
    }
}
