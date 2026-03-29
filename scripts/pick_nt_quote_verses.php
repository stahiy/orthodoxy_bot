<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$index = json_decode(file_get_contents($root . '/storage/bible_chapters_index.json'), true);
$ntBooks = array_keys($index['chapters']);
$start = array_search('От Матфея', $ntBooks, true);
$end = array_search('Откровение', $ntBooks, true);
if ($start === false || $end === false) {
	fwrite(STDERR, "NT bounds not found\n");
	exit(1);
}
$ntBooks = array_slice($ntBooks, $start, $end - $start + 1);

$existing = json_decode(file_get_contents($root . '/storage/new_testament_quotes.json'), true);
$used = [];
foreach ($existing['quotes'] as $q) {
	$used[$q['book'] . '|' . $q['chapter'] . '|' . $q['verse']] = true;
}

$candidates = [];
$fh = fopen($root . '/storage/bible_verses.jsonl', 'r');
if ($fh === false) {
	exit(1);
}
while (($line = fgets($fh)) !== false) {
	$row = json_decode($line, true);
	if (!is_array($row) || !isset($row['book'], $row['chapter'], $row['verse'], $row['text'])) {
		continue;
	}
	if (!in_array($row['book'], $ntBooks, true)) {
		continue;
	}
	$k = $row['book'] . '|' . $row['chapter'] . '|' . $row['verse'];
	if (isset($used[$k])) {
		continue;
	}
	$text = $row['text'];
	$len = mb_strlen($text);
	if ($len < 42 || $len > 240) {
		continue;
	}
	$t = trim($text);
	if (!preg_match('/[.!?…]$/u', $t)) {
		continue;
	}
	// Пропускаем обрывки стихов (продолжение предложения с новой строки в XML)
	if (preg_match('/^[а-яёa-z]/u', $t)) {
		continue;
	}
	$lower = mb_strtolower($text);
	if (str_contains($lower, 'благодать вам и мир от бога отца')) {
		continue;
	}
	if (str_contains($lower, 'благодать, милость, мир от бога')) {
		continue;
	}
	if (preg_match('/\bродил\b.*\bродил\b/u', $text)) {
		continue;
	}
	if (preg_match('/^итак всех родов от/u', $lower)) {
		continue;
	}
	if (preg_match('/^иосия родил|^иаков родил иосифа/u', $lower)) {
		continue;
	}
	if (preg_match('/^титу, истинному сыну/u', $lower)) {
		continue;
	}
	if (substr_count($text, ',') >= 9) {
		continue;
	}
	$candidates[] = [
		'book' => $row['book'],
		'chapter' => (int) $row['chapter'],
		'verse' => (int) $row['verse'],
		'text' => $text,
	];
}
fclose($fh);

$byBook = [];
foreach ($candidates as $c) {
	$byBook[$c['book']][] = $c;
}

$picked = [];
$round = 0;
$maxRounds = 800;
while (count($picked) < 100 && $round < $maxRounds) {
	foreach ($ntBooks as $book) {
		if (count($picked) >= 100) {
			break 2;
		}
		if (!isset($byBook[$book][$round])) {
			continue;
		}
		$picked[] = $byBook[$book][$round];
	}
	$round++;
}

if (count($picked) < 100) {
	$flat = $candidates;
	foreach ($picked as $p) {
		$used['pick|' . $p['book'] . '|' . $p['chapter'] . '|' . $p['verse']] = true;
	}
	foreach ($flat as $c) {
		if (count($picked) >= 100) {
			break;
		}
		$k = 'pick|' . $c['book'] . '|' . $c['chapter'] . '|' . $c['verse'];
		if (isset($used[$k])) {
			continue;
		}
		$picked[] = $c;
		$used[$k] = true;
	}
}

echo json_encode(array_slice($picked, 0, 100), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
