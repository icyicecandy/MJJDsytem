<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$coordinateMap = [
    'Agno' => [16.1156627, 119.7990260],
    'Aguilar' => [15.8890372, 120.2384199],
    'Alaminos City' => [16.1123968, 119.9734808],
    'Alcala' => [15.8443487, 120.5213759],
    'Anda' => [16.2896313, 119.9496392],
    'Asingan' => [16.0037829, 120.6702443],
    'Balungao' => [15.8977234, 120.6717900],
    'Bani' => [16.1839974, 119.8618795],
    'Basista' => [15.8531127, 120.4031042],
    'Bautista' => [15.8104339, 120.4761471],
    'Bayambang' => [15.8093238, 120.4570409],
    'Binalonan' => [16.0444475, 120.5917707],
    'Binmaley' => [16.0289195, 120.2684844],
    'Bolinao' => [16.3886595, 119.8932117],
    'Bugallon' => [15.9540627, 120.2149613],
    'Burgos' => [16.0593938, 119.8649284],
    'Calasiao' => [16.0089487, 120.3571939],
    'Dagupan City' => [16.0431813, 120.3341895],
    'Dasol' => [15.9902346, 119.8811438],
    'Infanta' => [15.8251315, 119.9056689],
    'Labrador' => [16.0254835, 120.1462065],
    'Laoac' => [16.0480995, 120.5471064],
    'Lingayen' => [16.0206363, 120.2307093],
    'Mabini' => [16.0698586, 119.9394828],
    'Malasiqui' => [15.9199144, 120.4138181],
    'Manaoag' => [16.0435664, 120.4873292],
    'Mangaldan' => [16.0702806, 120.4037803],
    'Mangatarem' => [15.7885355, 120.2938639],
    'Mapandan' => [16.0269118, 120.4537476],
    'Natividad' => [16.0427467, 120.7946807],
    'Pozorrubio' => [16.1102814, 120.5452288],
    'Rosales' => [15.8924255, 120.6329883],
    'San Carlos City' => [15.9260531, 120.3464235],
    'San Fabian' => [16.1221224, 120.4032449],
    'San Jacinto' => [16.0735396, 120.4370716],
    'San Manuel' => [16.0643792, 120.6681808],
    'San Nicolas' => [16.0693840, 120.7619537],
    'San Quintin' => [15.9843124, 120.8131571],
    'Santa Barbara' => [15.9999235, 120.4051274],
    'Santa Maria' => [15.9809907, 120.7004137],
    'Santo Tomas' => [15.8782280, 120.5860753],
    'Sison' => [16.1726711, 120.5096939],
    'Sual' => [16.0653569, 120.0962429],
    'Tayug' => [16.0275524, 120.7443637],
    'Umingan' => [15.9272139, 120.8407438],
    'Urbiztondo' => [15.8223901, 120.3293751],
    'Urdaneta City' => [15.9759995, 120.5668992],
    'Villasis' => [15.9015861, 120.5883085],
];

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: MJJDTelecomAddressCatalog/1.0\r\n",
        'timeout' => 30,
    ],
]);

function fetchJson(string $url, $context): array
{
    $json = file_get_contents($url, false, $context);

    if ($json === false) {
        throw new RuntimeException("Unable to fetch {$url}");
    }

    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function repairMojibake(string $value): string
{
    $value = trim($value);

    if (! preg_match('/[ÃÂ]/u', $value)) {
        return $value;
    }

    return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
}

function normalizeLocalityName(string $value): string
{
    return match (repairMojibake($value)) {
        'City of Alaminos' => 'Alaminos City',
        'City of Dagupan' => 'Dagupan City',
        'City of San Carlos' => 'San Carlos City',
        'City of Urdaneta' => 'Urdaneta City',
        default => repairMojibake($value),
    };
}

function catalogCode(string $name): string
{
    $code = strtoupper($name);
    $code = str_replace([' ', '.', '-', '(', ')'], ['_', '', '_', '', ''], $code);
    $code = preg_replace('/_+/', '_', $code);

    return trim($code, '_');
}

$localityResponse = fetchJson('https://psgc.cloud/api/v2/provinces/0105500000/cities-municipalities?per_page=100', $context);
$barangayResponse = fetchJson('https://psgc.cloud/api/v2/provinces/0105500000/barangays?per_page=2000', $context);

$barangaysByLocality = [];
foreach ($barangayResponse['data'] as $barangay) {
    $localityName = normalizeLocalityName($barangay['city_municipality']);
    $barangaysByLocality[$localityName][] = repairMojibake($barangay['name']);
}

$municipalities = [];
foreach ($localityResponse['data'] as $locality) {
    $name = normalizeLocalityName($locality['name']);
    [$lat, $lng] = $coordinateMap[$name] ?? [15.8949, 120.2863];

    $municipalities[] = [
        'code' => catalogCode($name),
        'psgc_code' => $locality['code'],
        'name' => $name,
        'lat' => $lat,
        'lng' => $lng,
        'zoom' => 13,
        'barangays' => $barangaysByLocality[$name] ?? [],
    ];
}

$config = [
    'regions' => [
        [
            'code' => 'R1',
            'psgc_code' => '0100000000',
            'name' => 'Ilocos Region',
            'lat' => 16.0832,
            'lng' => 120.4390,
            'zoom' => 8,
            'provinces' => [
                [
                    'code' => 'PANGASINAN',
                    'psgc_code' => '0105500000',
                    'name' => 'Pangasinan',
                    'lat' => 15.8949,
                    'lng' => 120.2863,
                    'zoom' => 9,
                    'municipalities' => $municipalities,
                ],
            ],
        ],
    ],
];

$export = var_export($config, true);
$export = preg_replace('/^([ ]*)array \(/m', '$1[', $export);
$export = preg_replace('/\)(,?)$/m', ']$1', $export);
$export = str_replace('  ', '    ', $export);

$contents = <<<PHP
<?php

// Service areas are intentionally limited to Pangasinan.
// Barangay names are sourced from PSGC Cloud's mirror of PSA PSGC data.
// Municipality coordinates are local starter points for map pin placement.
return {$export};

PHP;

file_put_contents($root . '/config/luzon_service_areas.php', $contents);

printf(
    "Wrote %d municipalities/cities and %d barangays.\n",
    count($municipalities),
    count($barangayResponse['data'])
);
