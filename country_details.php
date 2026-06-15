<?php

$qid = $_GET['country_qid'] ?? null;

if (!$qid || !preg_match('/^Q\d+$/', $qid)) {
    echo "<h1>Ошибка: Неверный или отсутствующий идентификатор страны.</h1>";
    exit;
}

$apiUrl = 'https://www.wikidata.org/w/api.php';

$params = [
    'action' => 'wbgetentities',
    'ids' => $qid,
    'format' => 'json',
    'languages' => 'ru',
    'props' => 'labels|descriptions|claims'
];

$url = $apiUrl . '?' . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'MyWikipediaApp/0.1 (https://example.com/contact)');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo "<h1>Ошибка: Не удалось получить данные из Wikidata.</h1>";
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || isset($data['error']) || !isset($data['entities'][$qid])) {
    echo "<h1>Ошибка: Некорректные данные от Wikidata или элемент не найден.</h1>";
    exit;
}

$entity = $data['entities'][$qid];

$label = $entity['labels']['ru']['value'] ?? $entity['labels']['en']['value'] ?? $qid;
$description = $entity['descriptions']['ru']['value'] ?? $entity['descriptions']['en']['value'] ?? 'Нет описания';

$claims = $entity['claims'];
$population = 'Не указано';
$populationDate = '';
if (isset($claims['P1082']) && !empty($claims['P1082'])) {
    $popClaim = $claims['P1082'][0];
    if (isset($popClaim['mainsnak']['datavalue']['value']['amount'])) {
        $population = number_format((int)ltrim($popClaim['mainsnak']['datavalue']['value']['amount'], '+'), 0, '.', ' ');
        foreach ($popClaim['qualifiers']['P585'] ?? [] as $qualifier) {
            if ($qualifier['datavalue']['type'] === 'time') {
                $dateStr = $qualifier['datavalue']['value']['time'];
                $populationDate = substr($dateStr, 0, 11); // YYYY-MM-DD
                break;
            }
        }
    }
}

$capitalLabel = 'Не указана';
if (isset($claims['P36']) && !empty($claims['P36'])) {
    $capClaim = $claims['P36'][0]; // Берём первую столицу
    $capEntityId = $capClaim['mainsnak']['datavalue']['value']['id'];

    $capApiUrl = 'https://www.wikidata.org/w/api.php';
    $capParams = [
        'action' => 'wbgetentities',
        'ids' => $capEntityId,
        'format' => 'json',
        'languages' => 'ru,en',
        'props' => 'labels'
    ];
    $capUrl = $capApiUrl . '?' . http_build_query($capParams);

    $capCh = curl_init();
    curl_setopt($capCh, CURLOPT_URL, $capUrl);
    curl_setopt($capCh, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($capCh, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($capCh, CURLOPT_USERAGENT, 'MyWikipediaApp/0.1 (https://example.com/contact)');
    $capResponse = curl_exec($capCh);
    $capHttpCode = curl_getinfo($capCh, CURLINFO_HTTP_CODE);
    curl_close($capCh);

    if ($capHttpCode === 200) {
        $capData = json_decode($capResponse, true);
        if (isset($capData['entities'][$capEntityId]['labels']['ru']['value'])) {
            $capitalLabel = $capData['entities'][$capEntityId]['labels']['ru']['value'];
        } elseif (isset($capData['entities'][$capEntityId]['labels']['en']['value'])) {
             $capitalLabel = $capData['entities'][$capEntityId]['labels']['en']['value'];
        }
    }
}

echo "<a href='index.php'>← Назад к списку стран</a>";
echo "<h1>Детали: $label ($qid)</h1>";
echo "<p><strong>Описание:</strong> $description</p>";
echo "<p><strong>Население:</strong> $population" . ($populationDate ? " (по состоянию на $populationDate)" : "") . "</p>";
echo "<p><strong>Столица:</strong> $capitalLabel</p>";