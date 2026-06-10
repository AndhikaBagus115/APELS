<?php

namespace App\Services\Csv;

use League\Csv\Writer;

/**
 * CsvQuestionExporter — pretty-printer for Question bank (Req 20.5, 34.4).
 *
 * Exports questions to CSV format matching the import grammar.
 * Round-trip property: export → import → same data (Req 20.6).
 *
 * Headers: question, option_a, option_b, option_c, option_d, correct_answer, type, tag, difficulty
 */
class CsvQuestionExporter
{
    public const HEADERS = [
        'question', 'option_a', 'option_b', 'option_c', 'option_d',
        'correct_answer', 'type', 'tag', 'difficulty',
    ];

    /**
     * Export questions to CSV string.
     *
     * @param  iterable  $questions  Collection or array of Question models/arrays.
     * @return string                UTF-8 CSV content.
     */
    public function toCsvString(iterable $questions): string
    {
        $writer = Writer::fromString();
        $writer->setOutputBOM(\League\Csv\Bom::Utf8->value);
        $writer->insertOne(self::HEADERS);

        foreach ($questions as $question) {
            $row = is_array($question) ? $question : $question->toArray();
            $writer->insertOne([
                $row['question'] ?? '',
                $row['option_a'] ?? '',
                $row['option_b'] ?? '',
                $row['option_c'] ?? '',
                $row['option_d'] ?? '',
                $row['correct_answer'] ?? '',
                $row['type'] ?? '',
                $row['tag'] ?? '',
                (string) ($row['difficulty'] ?? '1'),
            ]);
        }

        return $writer->toString();
    }

    /**
     * Export questions to a file.
     *
     * @param  iterable  $questions
     * @param  string    $path       Absolute file path.
     */
    public function toFile(iterable $questions, string $path): void
    {
        file_put_contents($path, $this->toCsvString($questions));
    }
}
