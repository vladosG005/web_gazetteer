<!DOCTYPE html>
<html lang="ru">
 <head>
  <meta charset="UTF-8">
  <title>Веб-справочник по географии</title>
  <link rel="stylesheet" href="style.css">
 </head>
 <body>
    <header>
        <div class="header-content">
            <h1>🌍 NetGazetteer</h1>
            <nav>
                <ul>
                    <li><a href="index.html">Главная</a></li>
                    <li><a href="countries_list.php">Страны</a></li>
                </ul>
            </nav>
        </div>
    </header>
    <main>
<?php

function fetchCountryIdsFromWikidata() {
    $sparqlEndpoint = 'https://query.wikidata.org/sparql';
    $query = '
        SELECT ?country ?countryLabel ?population ?capital ?capitalLabel WHERE {
          ?country wdt:P31 wd:Q6256;
                   wdt:P31 wd:Q3624078.
          MINUS { ?country wdt:P31 wd:Q3024240. }
           OPTIONAL { ?country wdt:P1082 ?population. }
            OPTIONAL { 
                ?country wdt:P36 ?capital.
            }
          SERVICE wikibase:label { bd:serviceParam wikibase:language "ru". }
        }
        ORDER BY ?countryLabel
    ';

    $params = [
        'query' => $query,
        'format' => 'json'
    ];

    $url = $sparqlEndpoint . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'MyWikipediaApp/0.1 (https://example.com/contact)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/sparql-results+json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception('cURL error: ' . $error);
    }

    if ($httpCode !== 200) {
        throw new Exception("SPARQL Query failed with HTTP code {$httpCode}. Response: " . $response);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON decode error: ' . json_last_error_msg());
    }

    if (!isset($data['results']['bindings'])) {
        throw new Exception('Invalid SPARQL response format: missing results.bindings');
    }

    $countries = [];
    foreach ($data['results']['bindings'] as $binding) {
        $qid = str_replace('http://www.wikidata.org/entity/', '', $binding['country']['value']);
        $label = $binding['countryLabel']['value'] ?? '';
        $capital = $binding['capitalLabel']['value'] ?? '';
        $population = $binding['population']['value'] ?? '';
        $countries[] = ['qid' => $qid, 'label' => $label, 'capital' => $capital, 'population' => $population];
    }

    return $countries;
}

    $rawCountries = fetchCountryIdsFromWikidata();

    $uniqueCountries = [];
$seenQids = [];

foreach ($rawCountries as $country) {
    $qid = $country['qid'];
    if (!in_array($qid, $seenQids)) {
        $uniqueCountries[] = $country;
        $seenQids[] = $qid;
    }
}

echo "<h2>Список стран:</h2>";
echo "<ul>";
foreach ($uniqueCountries as $country) {
    $label = htmlspecialchars($country['label'], ENT_QUOTES, 'UTF-8');
    $qid = htmlspecialchars($country['qid'], ENT_QUOTES, 'UTF-8');

    $link = "country_details.php?country_qid=" . urlencode($qid);
    echo "<li><a href='$link'>$label</a> ($qid)</li>";
}
echo "</ul>";


  ?>
  </main>

  <footer>
        <div class="footer-content">
            <p>Данные предоставлены из Wikidata.</p>
        </div>
    </footer>
 </body>
</html>