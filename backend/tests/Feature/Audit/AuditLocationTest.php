<?php

namespace Tests\Feature\Audit;

use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditLocationTest extends TestCase
{
    private function logWith(array $headers): array
    {
        $request = Request::create('/api/v1/test', 'POST', server: array_combine(
            array_map(fn (string $h) => 'HTTP_'.strtoupper(str_replace('-', '_', $h)), array_keys($headers)),
            array_values($headers),
        ));

        $log = (new AuditLogger(app(TenantContext::class), $request))->log('test.location');

        return [$log->country_code, $log->city];
    }

    public function test_location_is_saved_when_the_proxy_secret_matches(): void
    {
        config(['erp.geo_header_secret' => 'proxy-secret-123']);

        $this->assertSame(['PK', 'Lahore'], $this->logWith([
            'X-ERP-Geo-Secret' => 'proxy-secret-123',
            'X-ERP-Geo-Country' => 'pk',
            'X-ERP-Geo-City' => 'Lahore',
        ]));

        $this->assertSame(['AE', 'Dubai Marina'], $this->logWith([
            'X-ERP-Geo-Secret' => 'proxy-secret-123',
            'X-ERP-Geo-Country' => 'AE',
            'X-ERP-Geo-City' => 'Dubai%20Marina',
        ]));
    }

    public function test_location_from_an_untrusted_caller_is_ignored(): void
    {
        config(['erp.geo_header_secret' => 'proxy-secret-123']);
        $this->assertSame([null, null], $this->logWith([
            'X-ERP-Geo-Secret' => 'guess',
            'X-ERP-Geo-Country' => 'US',
            'X-ERP-Geo-City' => 'Fake City',
        ]));

        config(['erp.geo_header_secret' => '']);
        $this->assertSame([null, null], $this->logWith([
            'X-ERP-Geo-Secret' => '',
            'X-ERP-Geo-Country' => 'US',
            'X-ERP-Geo-City' => 'Fake City',
        ]));
    }

    public function test_invalid_country_codes_are_dropped(): void
    {
        config(['erp.geo_header_secret' => 'proxy-secret-123']);

        $this->assertSame([null, 'Karachi'], $this->logWith([
            'X-ERP-Geo-Secret' => 'proxy-secret-123',
            'X-ERP-Geo-Country' => 'PAK<script>',
            'X-ERP-Geo-City' => 'Karachi',
        ]));
    }
}
