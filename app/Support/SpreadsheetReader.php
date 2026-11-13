<?php

namespace App\Support;

use App\Exceptions\ImportException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of a .xlsx file, or a .csv file, into rows of plain strings.
 * No Composer packages needed: .xlsx is read with PHP's zip extension.
 */
class SpreadsheetReader
{
    private const NS_RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public static function read(string $path, string $extension): array
    {
        $rows = match (strtolower($extension)) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new ImportException('Unsupported file type. Upload a .xlsx or .csv file.'),
        };

        // Trim every cell and drop completely empty rows.
        $rows = array_map(fn ($row) => array_map(fn ($value) => trim((string) $value), $row), $rows);

        return array_values(array_filter($rows, fn ($row) => implode('', $row) !== ''));
    }

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new ImportException('Could not open the file.');
        }

        // Excel in French/European locales saves CSV with ";" instead of ",".
        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rows[0][0]); // strip Excel's BOM
        }

        // Files saved by older Excel may not be UTF-8.
        return array_map(fn ($row) => array_map(
            fn ($value) => mb_check_encoding((string) $value, 'UTF-8') ? $value : mb_convert_encoding((string) $value, 'UTF-8', 'Windows-1252'),
            $row
        ), $rows);
    }

    private static function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new ImportException('This .xlsx file could not be opened. Re-save it in Excel and try again.');
        }

        // Shared strings: most text cells point into this list.
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            foreach (self::xml($sharedXml)->xpath('//*[local-name()="si"]') as $item) {
                $text = '';
                foreach ($item->xpath('.//*[local-name()="t"]') as $t) {
                    $text .= (string) $t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName(self::firstSheetPath($zip));
        $zip->close();

        if ($sheetXml === false) {
            throw new ImportException('Could not find a worksheet in this file.');
        }

        $rows = [];
        foreach (self::xml($sheetXml)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $cells = [];
            foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                $index = self::columnIndex((string) $cell['r']) ?? count($cells);
                $type = (string) $cell['t'];
                $raw = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');

                $cells[$index] = match ($type) {
                    's' => $sharedStrings[(int) $raw] ?? '',
                    'inlineStr' => implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]'))),
                    default => $raw,
                };
            }

            if ($cells) {
                $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
            }
        }

        return $rows;
    }

    /** Finds the file of the first worksheet (usually xl/worksheets/sheet1.xml). */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbook = $zip->getFromName('xl/workbook.xml');
        $relations = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relations === false) {
            return $fallback;
        }

        $sheet = self::xml($workbook)->xpath('//*[local-name()="sheet"]')[0] ?? null;
        if (! $sheet) {
            return $fallback;
        }

        $relationId = (string) ($sheet->attributes(self::NS_RELATIONSHIPS)['id'] ?? '');
        foreach (self::xml($relations)->xpath('//*[local-name()="Relationship"]') as $relation) {
            if ((string) $relation['Id'] === $relationId) {
                $target = ltrim((string) $relation['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return $fallback;
    }

    /** "C7" → 2 (zero-based column index) */
    private static function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)/', $reference, $match)) {
            return null;
        }

        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private static function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            throw new ImportException('This file looks damaged. Re-save it in Excel and try again.');
        }

        return $xml;
    }
}