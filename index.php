<!DOCTYPE html>
<html lang="ru">
 <head>
  <meta charset="UTF-8">
  <title>Веб-справочник по географии</title>
 </head>
 <body>
  <?php

function fetchCountryIdsFromWikidata() {
    $sparqlEndpoint = 'https://query.wikidata.org/sparql';
    $query = '
        SELECT ?country ?countryLabel WHERE {
          ?country wdt:P31 wd:Q6256. # ?country instance of country (Q6256)
          SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }
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
        CURLOPT_USERAGENT => 'MyWikipediaApp/0.1 (https://example.com/contact)', // Важно!
        CURLOPT_HTTPHEADER => [
            'Accept: application/sparql-results+json' // Указываем ожидаемый тип контента
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
        $countries[] = ['qid' => $qid, 'label' => $label];
    }

    return $countries;
}

try {
    $allCountries = fetchCountryIdsFromWikidata();

    echo "Найдено " . count($allCountries) . " стран:\n";
    foreach ($allCountries as $index => $country) {
            echo "{$country['qid']}, {$country['label']}\n";
    }
}
catch (Exception $e) {
    echo "Произошла ошибка: " . $e->getMessage() . "\n";
}

  ?>
 </body>
</html>
