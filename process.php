<?php

use Ramsey\Uuid\Uuid;

$data = NLP['data'];

$tokens = $data['tokens'];

$knowledge = [];
foreach ($data['knowledge'] as $item) {
    $knowledge[$item['syncon']] = $item;
}

$entities = $data['entities'];

$userTitle = null;
$userTitlePos = null;

$userName = null;
$userNamePos = null;

$companyName = null;
$companyNamePos = null;

$countryName = null;

// Find Name
foreach ($tokens as $key => $token) {
    if ($token['type'] === 'NPR.NPH' && strpos($token['morphology'] ?? '', 'Gender') === 0) {
        list('text' => $userName, 'pos' => $userNamePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}

// Find Name
if (!$userName) {
    foreach ($tokens as $key => $token) {
        if ($token['type'] === 'NPR.NPH') {
            list('text' => $userName, 'pos' => $userNamePos) = findSentence($token['sentence'], $tokens);
            break;
        }
    }
}

// Find Title
foreach ($tokens as $token) {
    if (!isset($knowledge[$token['syncon']])) {
        continue;
    }

    $txt = $knowledge[$token['syncon']]['label'];
    if (strpos($txt, 'person.') === 0) { /*'person.leader', 'person.worker'*/
        list('text' => $userTitle, 'pos' => $userTitlePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}

// Find Company
foreach ($tokens as $token) {
    if (!isset($knowledge[$token['syncon']])) {
        continue;
    }

    $txt = $knowledge[$token['syncon']]['label'];
    if (strpos($txt, 'organization.') === 0) {
        list('text' => $companyName, 'pos' => $companyNamePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}

// Find Company
if (!$companyName) {
    foreach ($tokens as $token) {
        if ($token['type'] == 'NPR.ORG') {
            list('text' => $companyName, 'pos' => $companyNamePos) = findSentence($token['sentence'], $tokens);
            break;
        }
    }
}

// Find Company
if (!$companyName) {
    foreach ($tokens as $k => $token) {
        if ($userTitlePos) {
            if ($k >= $userTitlePos['from'] && $k <= $userTitlePos['to']) {
                continue;
            }
        }

        if (!isset($knowledge[$token['syncon']])) {
            continue;
        }

        $txt = $knowledge[$token['syncon']]['label'];
        if (strpos($txt, 'action.') === 0) { /*'action.professional_activity', 'action.service'*/
            list('text' => $companyName, 'pos' => $companyNamePos) = findSentence($token['sentence'], $tokens);
            break;
        }
    }
}

// Find Title
if (!$userTitle) {
    foreach ($tokens as $token) {
        if (!isset($knowledge[$token['syncon']])) {
            continue;
        }

        $txt = $knowledge[$token['syncon']]['label'];
        if ($txt === 'action') {
            list('text' => $userTitle, 'pos' => $userTitlePos) = findSentence($token['sentence'], $tokens);
            break;
        }
    }
}


// Find Name
if (!$userName && $userTitle) {
    for ($i = $userTitlePos['from'] - 1; $i < 0; $i++) {
        $token = $tokens[$i];

        if ($token['type'] != 'NPR') {
            continue;
        }

        if ($companyNamePos) {
            if ($i >= $companyNamePos['from'] && $i <= $companyNamePos['to']) {
                continue;
            }
        }

        list('text' => $userName, 'pos' => $userNamePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}

// Find Company
if (!$companyName) {
    foreach ($tokens as $key => $token) {
        if ($token['type'] != 'NPR') {
            continue;
        }

        if ($userNamePos) {
            if ($key >= $userNamePos['from'] && $key <= $userNamePos['to']) {
                continue;
            }
        }

        list('text' => $companyName, 'pos' => $companyNamePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}

function findSentence($sentence, $tokens) {
    $from = null;
    $to = null;
    $tok = [];
    foreach ($tokens as $key => $v) {
        if ($sentence == $v['sentence']) {
            if (!$from) {
                $from = $key;
            }

            $to = $key;
            $tok[] = $v;
        }
    }

    $text = array_reduce($tok, fn($c, $v) => $c . ucfirst($v['lemma']) . ' ', "");
    return ['dep' => $tok, 'text' => trim($text), 'pos' => [ 'from' => $from, 'to' => $to ]];
}

// Find Name
if (!$userName) {
    foreach ($tokens as $key => $token) {
        if ($token['type'] != 'NPR') {
            continue;
        }

        if (!isset($token[$key + 1])) {
            break;
        }

        list('dep' => $tok, 'pos' => $pos) = findSentence($token['sentence'], $tokens);

        $nextToken = reset($tok);
        if (in_array($nextToken['type'], ['NOU.PHO', 'NOU.WEB', 'NOU.MAI',  'NOU.ADR', 'NPR.GEO', 'NPR.BLD'])) {
            if ($companyNamePos) {
                if ($key >= $companyNamePos['from'] && $key <= $companyNamePos['to']) {
                    $userName = $companyName;
                    $userNamePos = $companyNamePos;

                    $companyName = null;
                    $companyNamePos = null;

                    break;
                }
            }

            $userName = trim(array_reduce($tok, fn($c, $v) => $c . ucfirst($v['lemma']) . ' ', ""));
            $userNamePos = $pos;
            break;
        }
    }
}

// Find Company
if (!$companyName) {
    foreach ($tokens as $key => $token) {
        if ($token['type'] != 'NPR') {
            continue;
        }

        if ($userNamePos) {
            if ($key >= $userNamePos['from'] && $key <= $userNamePos['to']) {
                continue;
            }
        }

        list('text' => $companyName, 'pos' => $companyNamePos) = findSentence($token['sentence'], $tokens);
        break;
    }
}


if ($companyName && !$userName) {
    $userName = $companyName;
    $companyName = null;
}


foreach ($tokens as $token) {
    if ($token['type'] == 'NPR.GEO') {
        if(isset($knowledge[$token['syncon']])) {
            if ($knowledge[$token['syncon']]['label'] === 'geographic_element.country') {
                $countryName = $token['lemma'];
            }
        }
    }
}

$address = null;
$addressKey = null;

foreach (['NOU.ADR', 'NPR.GEO'] as $value) {
    foreach ($tokens as $token) {
        if ($token['type'] == $value) {
            $sentence = $token['sentence'];

            $rAddress = [];
            while (true) {
                if (!isset($data['sentences'][$sentence])) {
                    break;
                }

                list('dep' => $tok, 'text' => $adr, 'pos' => $addressKey) = findSentence($sentence, $tokens);

                $found = false;
                foreach ($tok as $item) {
                    if (in_array($item['type'], ['NOU.ADR', 'NPR.GEO'])) {
                        $rAddress[] = $adr;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    break;
                }

                $sentence ++;
            }

            $address = implode("\n", $rAddress);
            break;
        }
    }

    if ($address) {
        break;
    }
}


function split_name($name) {
    $name = trim($name);
    $last_name = (strpos($name, ' ') === false) ? '' : preg_replace('#.*\s([\w-]*)$#', '$1', $name);
    $first_name = trim( preg_replace('#'.preg_quote($last_name,'#').'#', '', $name ) );
    return array($first_name, $last_name);
}

$names = split_name($userName);
$parsed = [
    '_id' => null,
    'image' => null,
    'firstName' => $names[0] ?? null,
    'lastName' => $names[1] ?? null,
    'company' => $companyName,
    'title' => $userTitle,
    'numbers' => [],
    'emails' => [],
    'websites' => [],
    'addresses' => [],
    'country' => $countryName,
    'notes' => null
];

foreach ($entities as $entity) {
    $mapping = [
        'PHO' => 'numbers', 'MAI' => 'emails', 'WEB' => ['websites', 'Website'], /*'ADR' => 'addresses', 'GEO' => 'addresses'*/
    ];

    $key = $mapping[$entity['type']] ?? null;

    if ($key) {
        $type = 'Work';
        if (is_array($key)) {
            $type = $key[1];
            $key = $key[0];
        }

        $value = $entity['lemma'];
        if (in_array($entity['type'], ['MAI', 'WEB'])) {
            $value = mb_strtolower(trim($value));
        }

        $parsed[$key][] = ['id' => Uuid::uuid4()->toString(), 'value' => $value, 'type' => $type];
    }
}

if ($address) {
    $parsed['addresses'][] = ['id' => Uuid::uuid4()->toString(), 'value' => $address, 'type' => 'Work'];
}


return $parsed;
