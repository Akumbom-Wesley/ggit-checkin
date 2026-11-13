<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Tiny .xlsx writer — no Composer packages needed, only PHP's zip extension.
 * Each sheet: ['name' => 'Sheet', 'rows' => [[header...], [row...], ...], 'widths' => [10, 30, ...]]
 * The first row is styled as a header, frozen, and gets filter arrows.
 */
class SimpleXlsx
{
    /** Builds the workbook and returns the path of a temporary .xlsx file. */
    public static function build(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Enable the PHP zip extension (extension=zip in php.ini) to export Excel files.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $count = count($sheets);
        $overrides = '';
        $sheetTags = '';
        $sheetRels = '';
        $definedNames = '';

        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $name = self::esc(mb_substr($sheet['name'], 0, 31));
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetTags .= '<sheet name="'.$name.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $sheetRels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';

            $range = self::range($sheet['rows']);
            if ($range) {
                [$from, $to] = explode(':', $range);
                $abs = '$'.preg_replace('/(\d+)/', '\$$1', $from).':$'.preg_replace('/(\d+)/', '\$$1', $to);
                $definedNames .= '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$i.'" hidden="1">\''.$name.'\'!'.$abs.'</definedName>';
            }

            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::sheetXml($sheet));
        }

        $zip->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>');

        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetTags.'</sheets>'
            .($definedNames ? '<definedNames>'.$definedNames.'</definedNames>' : '')
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$sheetRels
            .'<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // Style 0 = normal, style 1 = bold white text on GGIT purple (header row)
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF7A2E83"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $zip->close();

        return $path;
    }

    private static function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';

        if (! empty($sheet['widths'])) {
            $xml .= '<cols>';
            foreach (array_values($sheet['widths']) as $i => $w) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.(float) $w.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $rowNum = $r + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($row) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = self::col($c).$rowNum;
                $style = $r === 0 ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'"'.$style.' t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($range = self::range($sheet['rows'])) {
            $xml .= '<autoFilter ref="'.$range.'"/>';
        }

        return $xml.'</worksheet>';
    }

    private static function range(array $rows): ?string
    {
        $width = count($rows[0] ?? []);

        return $width ? 'A1:'.self::col($width - 1).max(count($rows), 1) : null;
    }

    /** 0 → A, 25 → Z, 26 → AA */
    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    private static function esc(string $v): string
    {
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $v) ?? '';

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}