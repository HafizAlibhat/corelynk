<?php

namespace App\Services;

class SearchService
{
    /**
     * Split a keyword string into normalized terms.
     */
    public static function normalizeTerms(?string $keyword): array
    {
        $keyword = trim((string) $keyword);
        if ($keyword === '') return [];
        $parts = preg_split('/\s+/u', $keyword) ?: [];
        $terms = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') $terms[] = $p;
        }
        return $terms;
    }

    /**
     * Apply keyword search to a CI4 Query Builder.
     * Each term must match at least one field (AND across terms, OR across fields).
     */
    public static function applyKeywordSearch($builder, ?string $keyword, array $fields): void
    {
        $terms = self::normalizeTerms($keyword);
        if (empty($terms) || empty($fields)) return;

        foreach ($terms as $term) {
            $builder->groupStart();
            foreach ($fields as $field) {
                if (is_string($field) && $field !== '') {
                    $builder->orLike($field, $term);
                }
            }
            $builder->groupEnd();
        }
    }

    /**
     * Filter an array of rows using keyword search across fields.
     */
    public static function filterRows(array $rows, ?string $keyword, array $fields): array
    {
        $terms = self::normalizeTerms($keyword);
        if (empty($terms) || empty($fields)) return $rows;

        $out = [];
        foreach ($rows as $row) {
            $hay = self::rowToSearchText($row, $fields);
            $ok = true;
            foreach ($terms as $t) {
                if (stripos($hay, $t) === false) { $ok = false; break; }
            }
            if ($ok) $out[] = $row;
        }
        return $out;
    }

    private static function rowToSearchText(array $row, array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            if (!is_string($f) || $f === '') continue;
            if (array_key_exists($f, $row)) {
                $parts[] = (string) $row[$f];
            }
        }
        return strtolower(implode(' ', $parts));
    }
}
