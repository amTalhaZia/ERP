<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Approximate country/city of the person making the request.
 *
 * The hosting edge in front of the frontend (Vercel) knows the visitor's
 * location; the frontend proxy forwards it in X-ERP-Geo-* headers together
 * with a shared secret (GEO_HEADER_SECRET). Without that secret the headers
 * are ignored, so a client calling the API directly cannot fake a location.
 * Locally (no secret) the location is simply unknown.
 */
class ClientLocation
{
    /** @return array{country_code: ?string, city: ?string} */
    public static function from(Request $request): array
    {
        $secret = (string) config('erp.geo_header_secret');
        $given = (string) $request->header('X-ERP-Geo-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $given)) {
            return ['country_code' => null, 'city' => null];
        }

        $country = strtoupper(trim((string) $request->header('X-ERP-Geo-Country', '')));
        $city = trim(rawurldecode((string) $request->header('X-ERP-Geo-City', '')));

        return [
            'country_code' => preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
            'city' => $city !== '' ? mb_substr(strip_tags($city), 0, 100) : null,
        ];
    }
}
