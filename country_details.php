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
    'languages' => 'ru,en',
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
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<h1>cURL ошибка: " . $error . "</h1>";
    exit;
}

if ($httpCode !== 200 || !$response) {
    echo "<h1>Ошибка: Не удалось получить данные из Wikidata (HTTP $httpCode).</h1>";
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE || isset($data['error']) || !isset($data['entities'][$qid])) {
    echo "<h1>Ошибка: Некорректные данные от Wikidata или элемент не найден.</h1>";
    exit;
}

$entity = $data['entities'][$qid];

$claims = $entity['claims'];

$label = $entity['labels']['ru']['value'] ?? $entity['labels']['en']['value'] ?? $qid;
$description = $entity['descriptions']['ru']['value'] ?? $entity['descriptions']['en']['value'] ?? 'Нет описания';

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

$area = 'Не указана';
$areaUnit = 'км²';
if (isset($claims['P2046']) && !empty($claims['P2046'])) {
    $areaClaim = $claims['P2046'][0];
    if (isset($areaClaim['mainsnak']['datavalue']['value']['amount'])) {
        $rawArea = ltrim($areaClaim['mainsnak']['datavalue']['value']['amount'], '+');
        $area = number_format((float)$rawArea, 2, '.', ' ') . ' ' . $areaUnit;
    }
}

$gdp = 'Не указан';
$gdpCurrency = '$';
if (isset($claims['P2131']) && !empty($claims['P2131'])) {
    $gdpClaim = $claims['P2131'][0];
    if (isset($gdpClaim['mainsnak']['datavalue']['value']['amount'])) {
        $rawGdp = ltrim($gdpClaim['mainsnak']['datavalue']['value']['amount'], '+');
        $gdp = $gdpCurrency . ' ' . number_format((float)$rawGdp, 2, '.', ' '); // Форматируем с 2 знаками после запятой
    }
}

$administrativeDivisions = 'Информация отсутствует';
if (isset($claims['P150']) && !empty($claims['P150'])) {
    $divisionItems = [];
    foreach ($claims['P150'] as $divisionClaim) {
        if (isset($divisionClaim['mainsnak']['datavalue']['value']['id'])) {
            $divisionQid = $divisionClaim['mainsnak']['datavalue']['value']['id'];

            $divisionLabel = $divisionQid; // Значение по умолчанию
            $divisionApiUrl = 'https://www.wikidata.org/w/api.php';
            $divisionParams = [
                'action' => 'wbgetentities',
                'ids' => $divisionQid,
                'format' => 'json',
                'languages' => 'ru,en',
                'props' => 'labels'
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
    $headClaim = null;
    foreach ($claims['P6'] as $potentialClaim) {
        if (isset($potentialClaim['mainsnak']['datavalue']['value']['id'])) {
            $headClaim = $potentialClaim;
            break;
        }
    }

    if ($headClaim) {
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
$potentialGovHeadQid = null;

if (isset($claims['P691']) && !empty($claims['P691'])) {
    $govHeadClaim = $claims['P691'][0];
    if (isset($govHeadClaim['mainsnak']['datavalue']['value']['id'])) {
         $potentialGovHeadQid = $govHeadClaim['mainsnak']['datavalue']['value']['id'];
    }
}

if (!$potentialGovHeadQid && isset($claims['P742']) && !empty($claims['P742'])) {
    $pmClaim = $claims['P742'][0];
    if (isset($pmClaim['mainsnak']['datavalue']['value']['id'])) {
         $potentialGovHeadQid = $pmClaim['mainsnak']['datavalue']['value']['id'];
    }
}

if ($potentialGovHeadQid) {
    $govHeadLabel = $potentialGovHeadQid;
    $govHeadApiUrl = 'https://www.wikidata.org/w/api.php';
    $govHeadParams = [
        'action' => 'wbgetentities',
        'ids' => $potentialGovHeadQid,
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
        if (isset($govHeadData['entities'][$potentialGovHeadQid]['labels']['ru']['value'])) {
            $govHeadLabel = $govHeadData['entities'][$potentialGovHeadQid]['labels']['ru']['value'];
        } elseif (isset($govHeadData['entities'][$potentialGovHeadQid]['labels']['en']['value'])) {
             $govHeadLabel = $govHeadData['entities'][$potentialGovHeadQid]['labels']['en']['value'];
        }
    }
    $headOfGovernment = $govHeadLabel;
}

echo "<a href='countries_list.php'>← Назад к списку стран</a>";
echo "<h1>Детали: $label ($qid)</h1>";
echo "<p><strong>Описание:</strong> $description</p>";
echo "<p><strong>Население:</strong> $population" . ($populationDate ? " (по состоянию на $populationDate)" : "") . "</p>";
echo "<p><strong>Площадь:</strong> $area</p>";
echo "<p><strong>Номинальный ВВП:</strong> $gdp</p>";
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