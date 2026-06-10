<?php

/**
 * Property 10: CSV Question round-trip (Req 20.6, 34.4).
 *
 * For any valid Question dataset Q:
 *   parse(toCsvString(Q))['valid'] == Q  (field-by-field)
 *   parse(toCsvString(Q))['errors'] == []
 */

use App\Services\Csv\CsvQuestionExporter;
use App\Services\Csv\CsvQuestionImporter;

beforeEach(function () {
    $this->exporter = new CsvQuestionExporter();
    $this->importer = new CsvQuestionImporter();
});

function makeQuestion(array $overrides = []): array
{
    return array_merge([
        'question'       => 'What is the correct form of the verb "to be" in present simple?',
        'option_a'       => 'am',
        'option_b'       => 'is',
        'option_c'       => 'are',
        'option_d'       => 'were',
        'correct_answer' => 'a',
        'type'           => 'grammar',
        'tag'            => 'basic',
        'difficulty'     => 1,
        'is_active'      => true,
    ], $overrides);
}

// ---- Round-trip: single question ----

test('Property 10: single valid question survives round-trip', function () {
    $q = makeQuestion();
    $csv = $this->exporter->toCsvString([$q]);
    $result = $this->importer->parse($csv);

    expect($result['errors'])->toBeEmpty();
    expect($result['valid'])->toHaveCount(1);

    $parsed = $result['valid'][0];
    foreach (CsvQuestionExporter::HEADERS as $field) {
        if ($field === 'difficulty') {
            expect($parsed[$field])->toBe((int) $q[$field]);
        } else {
            expect($parsed[$field])->toBe((string) $q[$field]);
        }
    }
})->group('property');

// ---- Round-trip: multiple questions ----

test('Property 10: multiple questions preserve count and fields', function () {
    $questions = [
        makeQuestion(['correct_answer' => 'a', 'type' => 'grammar', 'tag' => 'basic', 'difficulty' => 1]),
        makeQuestion(['correct_answer' => 'b', 'type' => 'vocabulary', 'tag' => 'intermediate', 'difficulty' => 3]),
        makeQuestion(['correct_answer' => 'c', 'type' => 'grammar', 'tag' => 'advanced', 'difficulty' => 5]),
        makeQuestion(['correct_answer' => 'd', 'type' => 'vocabulary', 'tag' => 'basic', 'difficulty' => 2]),
    ];

    $csv = $this->exporter->toCsvString($questions);
    $result = $this->importer->parse($csv);

    expect($result['errors'])->toBeEmpty();
    expect($result['valid'])->toHaveCount(4);

    foreach ($questions as $i => $original) {
        $parsed = $result['valid'][$i];
        expect($parsed['correct_answer'])->toBe($original['correct_answer']);
        expect($parsed['type'])->toBe($original['type']);
        expect($parsed['tag'])->toBe($original['tag']);
        expect($parsed['difficulty'])->toBe((int) $original['difficulty']);
    }
})->group('property');

// ---- Round-trip: all enum variants ----

test('Property 10: all correct_answer variants survive round-trip', function () {
    foreach (['a', 'b', 'c', 'd'] as $answer) {
        $q = makeQuestion(['correct_answer' => $answer]);
        $csv = $this->exporter->toCsvString([$q]);
        $result = $this->importer->parse($csv);

        expect($result['errors'])->toBeEmpty();
        expect($result['valid'][0]['correct_answer'])->toBe($answer);
    }
})->group('property');

test('Property 10: all type variants survive round-trip', function () {
    foreach (['grammar', 'vocabulary'] as $type) {
        $q = makeQuestion(['type' => $type]);
        $csv = $this->exporter->toCsvString([$q]);
        $result = $this->importer->parse($csv);

        expect($result['errors'])->toBeEmpty();
        expect($result['valid'][0]['type'])->toBe($type);
    }
})->group('property');

test('Property 10: all tag variants survive round-trip', function () {
    foreach (['basic', 'intermediate', 'advanced'] as $tag) {
        $q = makeQuestion(['tag' => $tag]);
        $csv = $this->exporter->toCsvString([$q]);
        $result = $this->importer->parse($csv);

        expect($result['errors'])->toBeEmpty();
        expect($result['valid'][0]['tag'])->toBe($tag);
    }
})->group('property');

test('Property 10: all difficulty values 1-5 survive round-trip', function () {
    foreach (range(1, 5) as $difficulty) {
        $q = makeQuestion(['difficulty' => $difficulty]);
        $csv = $this->exporter->toCsvString([$q]);
        $result = $this->importer->parse($csv);

        expect($result['errors'])->toBeEmpty();
        expect($result['valid'][0]['difficulty'])->toBe($difficulty);
    }
})->group('property');

// ---- Round-trip: special characters in text ----

test('Property 10: question text with commas survives round-trip', function () {
    $q = makeQuestion(['question' => 'Choose the correct form: "I am, you are, he is"']);
    $csv = $this->exporter->toCsvString([$q]);
    $result = $this->importer->parse($csv);

    expect($result['errors'])->toBeEmpty();
    expect($result['valid'][0]['question'])->toBe($q['question']);
})->group('property');

// ---- Importer: invalid rows do NOT break valid rows ----

test('Importer: invalid row skipped, valid rows processed (Req 20.4)', function () {
    $validQ = makeQuestion();
    $validCsv = $this->exporter->toCsvString([$validQ]);

    // Inject invalid row (bad correct_answer)
    $lines = explode("\n", trim($validCsv));
    $header = $lines[0];
    $validRow = $lines[1];
    $invalidRow = 'Bad question,,,,,"x","grammar","basic",1'; // option_b-d empty, correct_answer=x

    $mixedCsv = $header . "\n" . $validRow . "\n" . $invalidRow . "\n";
    $result = $this->importer->parse($mixedCsv);

    expect($result['valid'])->toHaveCount(1);
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0]['row'])->toBe(3);
});
