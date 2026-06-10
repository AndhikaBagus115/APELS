<?php

namespace App\Services\Csv;

use League\Csv\Reader;

/**
 * CsvQuestionImporter — parser for Question bank CSV import (Req 20.2-20.4).
 *
 * Validates each row against rules:
 *   - 9 columns required (matching HEADERS)
 *   - correct_answer ∈ {a, b, c, d}
 *   - type ∈ {grammar, vocabulary}
 *   - tag ∈ {basic, intermediate, advanced}
 *   - difficulty integer 1..5
 *   - question, option_a-d non-empty
 *
 * Returns ['valid' => array[], 'errors' => [{row, reasons}]].
 * Valid rows are NOT persisted here — caller decides (Req 20.4: partial success).
 */
class CsvQuestionImporter
{
    private const VALID_CORRECT_ANSWERS = ['a', 'b', 'c', 'd'];
    private const VALID_TYPES = ['grammar', 'vocabulary'];
    private const VALID_TAGS = ['basic', 'intermediate', 'advanced'];

    /**
     * Parse CSV content and validate rows.
     *
     * @param  string  $csvContent  Raw CSV string (UTF-8).
     * @return array{valid: list<array>, errors: list<array{row: int, reasons: list<string>}>}
     */
    public function parse(string $csvContent): array
    {
        $valid = [];
        $errors = [];

        // Strip BOM if present
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);

        $reader = Reader::fromString($csvContent);
        $reader->setHeaderOffset(0);

        $rowNumber = 1; // 1-indexed (header = 0)

        foreach ($reader->getRecords() as $record) {
            $rowNumber++;
            $reasons = $this->validateRow($record);

            if (empty($reasons)) {
                $valid[] = [
                    'question'       => trim($record['question'] ?? ''),
                    'option_a'       => trim($record['option_a'] ?? ''),
                    'option_b'       => trim($record['option_b'] ?? ''),
                    'option_c'       => trim($record['option_c'] ?? ''),
                    'option_d'       => trim($record['option_d'] ?? ''),
                    'correct_answer' => strtolower(trim($record['correct_answer'] ?? '')),
                    'type'           => strtolower(trim($record['type'] ?? '')),
                    'tag'            => strtolower(trim($record['tag'] ?? '')),
                    'difficulty'     => (int) ($record['difficulty'] ?? 1),
                    'is_active'      => true,
                ];
            } else {
                $errors[] = ['row' => $rowNumber, 'reasons' => $reasons];
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Validate a single CSV row.
     *
     * @return list<string>  Array of validation failure reasons (empty = valid).
     */
    private function validateRow(array $record): array
    {
        $reasons = [];

        // Required text fields
        foreach (['question', 'option_a', 'option_b', 'option_c', 'option_d'] as $field) {
            if (empty(trim($record[$field] ?? ''))) {
                $reasons[] = "{$field} wajib diisi";
            }
        }

        // correct_answer
        $ca = strtolower(trim($record['correct_answer'] ?? ''));
        if (!in_array($ca, self::VALID_CORRECT_ANSWERS, true)) {
            $reasons[] = "correct_answer harus salah satu dari: a, b, c, d";
        }

        // type
        $type = strtolower(trim($record['type'] ?? ''));
        if (!in_array($type, self::VALID_TYPES, true)) {
            $reasons[] = "type harus salah satu dari: grammar, vocabulary";
        }

        // tag
        $tag = strtolower(trim($record['tag'] ?? ''));
        if (!in_array($tag, self::VALID_TAGS, true)) {
            $reasons[] = "tag harus salah satu dari: basic, intermediate, advanced";
        }

        // difficulty
        $difficulty = (int) ($record['difficulty'] ?? 0);
        if ($difficulty < 1 || $difficulty > 5) {
            $reasons[] = "difficulty harus integer 1-5";
        }

        return $reasons;
    }
}
