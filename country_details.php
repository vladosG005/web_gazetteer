<!DOCTYPE html>
<html lang="ru">
 <head>
  <meta charset="UTF-8">
  <title>Веб-справочник по географии</title>
  <link rel="stylesheet" href="style.css">
  <link rel="icon" type="image/png" href="/web_gazetteer_logo.png">
 </head>
 <body>
    <header>
        <div class="header-content">
            <h1><a href="index.html">🌍 NetGazetteer</a></h1>
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
    $latestDate = null;
    $latestPopValue = null;
    $latestClaimIndex = null;

    foreach ($claims['P1082'] as $index => $claim) {
        $currentDate = null;

        if (isset($claim['qualifiers']['P585']) && !empty($claim['qualifiers']['P585'])) {
            $qualifierSnak = $claim['qualifiers']['P585'][0];
            if ($qualifierSnak['datatype'] === 'time' && isset($qualifierSnak['datavalue']['value']['time'])) {
                $timeString = $qualifierSnak['datavalue']['value']['time'];
                $year = (int)substr($timeString, 1, 4);
                $month = (int)substr($timeString, 6, 2);
                $day = (int)substr($timeString, 9, 2);

                if ($month === 0 && $day === 0) {
                    $currentDate = mktime(0, 0, 0, 1, 1, $year);
                } elseif ($day === 0) {
                    $currentDate = mktime(0, 0, 0, $month, 1, $year);
                } else {
                    $currentDate = mktime(0, 0, 0, $month, $day, $year);
                }
            }
        } else {
            continue;
        }

        if ($currentDate !== null && ($latestDate === null || $currentDate > $latestDate)) {
            $latestDate = $currentDate;
            if (isset($claim['mainsnak']['datavalue']['value']['amount'])) {
                $latestPopValue = (int)ltrim($claim['mainsnak']['datavalue']['value']['amount'], '+');
                $latestClaimIndex = $index;
            }
        }
    }

    if ($latestPopValue !== null && $latestClaimIndex !== null) {
        $population = number_format($latestPopValue, 0, '.', ' ');

        $originalTimeString = $claims['P1082'][$latestClaimIndex]['qualifiers']['P585'][0]['datavalue']['value']['time'];
        $year = (int)substr($originalTimeString, 1, 4);
        $month = (int)substr($originalTimeString, 6, 2);
        $day = (int)substr($originalTimeString, 9, 2);

        if ($month === 0 && $day === 0) {
            $populationDate = (string)$year;
        } elseif ($day === 0) {
            $populationDate = sprintf("%04d-%02d", $year, $month);
        } else {
            $populationDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
    }
}

$capitalLabel = 'Не указана';
if (isset($claims['P36']) && !empty($claims['P36'])) {
    $capClaim = $claims['P36'][0];
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

echo "<a href='countries_list.php'>← Назад к списку стран</a>";
echo "<h1>Детали: $label</h1>";
echo "<p><strong>Описание:</strong> $description</p>";
echo "<p><strong>Население:</strong> $population" . ($populationDate ? " (по состоянию на $populationDate)" : "") . "</p>";
echo "<p><strong>Столица:</strong> $capitalLabel</p>";
?>
</main>

  <footer>
        <div class="footer-content">
            <p>Данные предоставлены из Wikidata.</p>
        </div>
    </footer>
 </body>
</html>