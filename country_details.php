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

$area = 'Не указана';
$areaUnit = 'км²';
if (isset($claims['P2046']) && !empty($claims['P2046'])) {
    $areaClaim = $claims['P2046'][0]; // Берём первое значение
    if (isset($areaClaim['mainsnak']['datavalue']['value']['amount'])) {
        $rawArea = ltrim($areaClaim['mainsnak']['datavalue']['value']['amount'], '+');
        $unitUri = $areaClaim['mainsnak']['datavalue']['value']['unit'];
        $area = number_format((float)$rawArea, 2, '.', ' ') . ' ' . $areaUnit; // Форматируем с 2 знаками после запятой
    }
}

$gdpPerCapita = 'Не указан';
$gdpCurrency = '$';
if (isset($claims['P2132']) && !empty($claims['P2132'])) {
    $gdpClaim = $claims['P2132'][0]; // Берём первое значение
    if (isset($gdpClaim['mainsnak']['datavalue']['value']['amount'])) {
        $rawGdp = ltrim($gdpClaim['mainsnak']['datavalue']['value']['amount'], '+');
        $gdpPerCapita = $gdpCurrency . ' ' . number_format((float)$rawGdp, 2, '.', ' '); // Форматируем с 2 знаками после запятой
    }
}

$administrativeDivisions = 'Информация отсутствует';
if (isset($claims['P150']) && !empty($claims['P150'])) {
    $divisionItems = [];
    foreach ($claims['P150'] as $divisionClaim) {
        if (isset($divisionClaim['mainsnak']['datavalue']['value']['id'])) {
            $divisionQid = $divisionClaim['mainsnak']['datavalue']['value']['id'];

            $divisionLabel = $divisionQid;
            $divisionApiUrl = 'https://www.wikidata.org/w/api.php';
            $divisionParams = [
                'action' => 'wbgetentities',
                'ids' => $divisionQid,
                'format' => 'json',
                'languages' => 'ru,en',
                'props' => 'labels' // Запрашиваем только метки
            ];
            $divisionUrl = $divisionApiUrl . '?' . http_build_query($divisionParams);

            $divCh = curl_init();
            curl_setopt($divCh, CURLOPT_URL, $divisionUrl);
            curl_setopt($divCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($divCh, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($divCh, CURLOPT_USERAGENT, 'MyWikipediaApp/0.1 (https://example.com/contact)');
            $divResponse = curl_exec($divCh);
            $divHttpCode = curl_getinfo($divCh, CURLINFO_HTTP_CODE);
            curl_close($divCh);

            if ($divHttpCode === 200) {
                $divData = json_decode($divResponse, true);
                if (isset($divData['entities'][$divisionQid]['labels']['ru']['value'])) {
                    $divisionLabel = $divData['entities'][$divisionQid]['labels']['ru']['value'];
                } elseif (isset($divData['entities'][$divisionQid]['labels']['en']['value'])) {
                     $divisionLabel = $divData['entities'][$divisionQid]['labels']['en']['value'];
                }
            }

            $divisionItems[] = $divisionLabel;
        }
    }
    if (!empty($divisionItems)) {
        $displayCount = 10;
        $truncatedDivisions = array_slice($divisionItems, 0, $displayCount);
        $administrativeDivisions = implode(', ', $truncatedDivisions);
        if (count($divisionItems) > $displayCount) {
            $administrativeDivisions .= " и ещё " . (count($divisionItems) - $displayCount);
        }
    }
}

$headOfState = 'Не указан';
if (isset($claims['P6']) && !empty($claims['P6'])) {
    $headClaim = $claims['P6'][0];
    if (isset($headClaim['mainsnak']['datavalue']['value']['id'])) {
        $headQid = $headClaim['mainsnak']['datavalue']['value']['id'];

        $headLabel = $headQid;
        $headApiUrl = 'https://www.wikidata.org/w/api.php';
        $headParams = [
            'action' => 'wbgetentities',
            'ids' => $headQid,
            'format' => 'json',
            'languages' => 'ru,en',
            'props' => 'labels'
        ];
        $headUrl = $headApiUrl . '?' . http_build_query($headParams);

        $headCh = curl_init();
        curl_setopt($headCh, CURLOPT_URL, $headUrl);
        curl_setopt($headCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($headCh, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($headCh, CURLOPT_USERAGENT, 'MyWikipediaApp/0.1 (https://example.com/contact)');
        $headResponse = curl_exec($headCh);
        $headHttpCode = curl_getinfo($headCh, CURLINFO_HTTP_CODE);
        curl_close($headCh);

        if ($headHttpCode === 200) {
            $headData = json_decode($headResponse, true);
            if (isset($headData['entities'][$headQid]['labels']['ru']['value'])) {
                $headLabel = $headData['entities'][$headQid]['labels']['ru']['value'];
            } elseif (isset($headData['entities'][$headQid]['labels']['en']['value'])) {
                 $headLabel = $headData['entities'][$headQid]['labels']['en']['value'];
            }
        }
        $headOfState = $headLabel;
    }
}

$headOfGovernment = 'Не указан';
if (isset($claims['P691']) && !empty($claims['P691'])) {
    $govHeadClaim = $claims['P691'][0]; // Берём первое значение
    if (isset($govHeadClaim['mainsnak']['datavalue']['value']['id'])) {
        $govHeadQid = $govHeadClaim['mainsnak']['datavalue']['value']['id'];

        $govHeadLabel = $govHeadQid;
        $govHeadApiUrl = 'https://www.wikidata.org/w/api.php';
        $govHeadParams = [
            'action' => 'wbgetentities',
            'ids' => $govHeadQid,
            'format' => 'json',
            'languages' => 'ru,en',
            'props' => 'labels'
        ];
        $govHeadUrl = $govHeadApiUrl . '?' . http_build_query($govHeadParams);

        $govHeadCh = curl_init();
        curl_setopt($govHeadCh, CURLOPT_URL, $govHeadUrl);
        curl_setopt($govHeadCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($govHeadCh, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($govHeadCh, CURLOPT_USERAGENT, 'MyWikipediaApp/0.1 (https://example.com/contact)');
        $govHeadResponse = curl_exec($govHeadCh);
        $govHeadHttpCode = curl_getinfo($govHeadCh, CURLINFO_HTTP_CODE);
        curl_close($govHeadCh);

        if ($govHeadHttpCode === 200) {
            $govHeadData = json_decode($govHeadResponse, true);
            if (isset($govHeadData['entities'][$govHeadQid]['labels']['ru']['value'])) {
                $govHeadLabel = $govHeadData['entities'][$govHeadQid]['labels']['ru']['value'];
            } elseif (isset($govHeadData['entities'][$govHeadQid]['labels']['en']['value'])) {
                 $govHeadLabel = $govHeadData['entities'][$govHeadQid]['labels']['en']['value'];
            }
        }
        $headOfGovernment = $govHeadLabel;
    }
}

echo "<a href='index.php'>← Назад к списку стран</a>";
echo "<h1>Детали: $label ($qid)</h1>";
echo "<p><strong>Описание:</strong> $description</p>";
echo "<p><strong>Население:</strong> $population" . ($populationDate ? " (по состоянию на $populationDate)" : "") . "</p>";
echo "<p><strong>Площадь:</strong> $area</p>";
echo "<p><strong>Номинальный ВВП на душу населения:</strong> $gdpPerCapita</p>";
echo "<p><strong>Административное деление (регионы, штаты и т.д.):</strong> $administrativeDivisions</p>";
echo "<p><strong>Глава государства:</strong> $headOfState</p>";
echo "<p><strong>Глава правительства:</strong> $headOfGovernment</p>";

?>
</main>

  <footer>
        <div class="footer-content">
            <p>Данные предоставлены из Wikidata.</p>
        </div>
    </footer>
 </body>
</html>