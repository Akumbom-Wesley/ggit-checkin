<?php

namespace App\Services;

use App\Exceptions\ImportException;
use App\Models\Participant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns spreadsheet rows into participants.
 * Columns are recognised by their header text, so a Google Forms export works without editing.
 */
class ParticipantImporter
{
    /** Header keywords for each field, checked in this order (most specific first). */
    private const COLUMN_KEYWORDS = [
        'email' => ['email', 'e-mail', 'mail'],
        'phone' => ['phone', 'whatsapp', 'mobile', 'tel', 'contact'],
        'partner_name' => ['partner', 'sponsor', 'organisation', 'organization', 'company', 'institution'],
        'category' => ['category', 'type', 'group'],
        'notes' => ['anything', 'note', 'comment', 'remark'],
        'payment_proof' => ['payment', 'screenshot', 'receipt'],
        'registered_at' => ['timestamp', 'registered', 'date'],
        'first_name' => ['first name', 'firstname', 'given name'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name'],
        'full_name' => ['full name', 'name', 'participant'],
    ];

    /** Form answers that mean "nothing to note". */
    private const EMPTY_ANSWERS = ['', 'none', 'no', 'noo', 'n/a', 'na', 'nil', 'nothing', '-', '.'];

    /**
     * @param  array  $rows  First row must be the header.
     * @return array{imported:int, duplicates:int, empty:int, total_rows:int, duplicate_names:array, columns:array}
     */
    public function import(array $rows, string $defaultCategory, ?string $defaultPartner, bool $skipDuplicates = true): array
    {
        if (count($rows) < 2) {
            throw new ImportException('The file has no participant rows under the header row.');
        }

        $header = array_shift($rows);
        $columns = $this->mapColumns($header);

        if (! isset($columns['full_name']) && ! isset($columns['first_name'])) {
            throw new ImportException('No name column found. The first row must contain a header such as "Full name".');
        }

        $report = [
            'imported' => 0,
            'duplicates' => 0,
            'empty' => 0,
            'total_rows' => count($rows),
            'duplicate_names' => [],
            'columns' => array_map(fn ($index) => $header[$index], $columns),
        ];

        // Everyone already in the database, keyed for fast duplicate checks.
        $knownPhones = Participant::whereNotNull('phone_key')->pluck('phone_key')->flip()->all();
        $knownEmails = Participant::whereNotNull('email')->pluck('email')->flip()->all();

        DB::transaction(function () use ($rows, $columns, $defaultCategory, $defaultPartner, $skipDuplicates, &$knownPhones, &$knownEmails, &$report) {
            foreach ($rows as $row) {
                $value = fn (string $field) => isset($columns[$field]) ? trim((string) ($row[$columns[$field]] ?? '')) : '';

                $name = $value('full_name') ?: trim($value('first_name').' '.$value('last_name'));
                $name = preg_replace('/\s+/', ' ', $name);
                if (mb_strlen($name) < 2) {
                    $report['empty']++;
                    continue;
                }

                $phone = $this->normalisePhone($value('phone'));
                $phoneKey = Participant::phoneKey($phone);
                $email = Str::lower($value('email')) ?: null;

                $isDuplicate = ($phoneKey && isset($knownPhones[$phoneKey])) || ($email && isset($knownEmails[$email]));
                if ($skipDuplicates && $isDuplicate) {
                    $report['duplicates']++;
                    if (count($report['duplicate_names']) < 25) {
                        $report['duplicate_names'][] = $name;
                    }
                    continue;
                }

                $category = $this->normaliseCategory($value('category'), $defaultCategory);
                $partner = $value('partner_name') ?: ($category === 'partner' ? ($defaultPartner ?: null) : null);
                $notes = $value('notes');

                // Imported people paid before the event: marked paid, amount not tracked.
                Participant::create([
                    'full_name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'category' => $category,
                    'partner_name' => $partner,
                    'notes' => in_array(Str::lower($notes), self::EMPTY_ANSWERS, true) ? null : $notes,
                    'payment_proof' => $value('payment_proof') ?: null,
                    'paid' => true,
                    'amount' => 0,
                    'paid_at' => null,
                    'registered_at' => $this->parseDate($value('registered_at')),
                ]);

                if ($phoneKey) {
                    $knownPhones[$phoneKey] = true;
                }
                if ($email) {
                    $knownEmails[$email] = true;
                }
                $report['imported']++;
            }
        });

        return $report;
    }

    /** @return array<string,int> field => column index */
    private function mapColumns(array $header): array
    {
        $columns = [];
        $taken = [];

        foreach (self::COLUMN_KEYWORDS as $field => $keywords) {
            foreach ($header as $index => $title) {
                $title = Str::lower(trim((string) $title));
                if ($title === '' || isset($taken[$index])) {
                    continue;
                }
                foreach ($keywords as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $columns[$field] = $index;
                        $taken[$index] = true;
                        continue 3; // next field
                    }
                }
            }
        }

        return $columns;
    }

    private function normalisePhone(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw) && stripos($raw, 'e') !== false) {
            $raw = number_format((float) $raw, 0, '', ''); // 6.77E+08 from Excel
        }
        $raw = preg_replace('/\.0+$/', '', $raw); // 677138370.0 from Excel

        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) === 12 && str_starts_with($digits, '237')) {
            $digits = substr($digits, 3); // +237 6XX → 6XX
        }

        return $digits !== '' ? $digits : null;
    }

    private function normaliseCategory(string $raw, string $default): string
    {
        $value = Str::lower($raw);

        return match (true) {
            $value === '' => $default,
            str_contains($value, 'partner'), str_contains($value, 'sponsor') => 'partner',
            str_contains($value, 'speak'), str_contains($value, 'panel'), str_contains($value, 'moderat'), str_contains($value, 'facilitat') => 'speaker',
            str_contains($value, 'invit'), str_contains($value, 'guest'), str_contains($value, 'vip') => 'invited',
            str_contains($value, 'walk') => 'walkin',
            str_contains($value, 'regist') => 'registered',
            default => $default,
        };
    }

    private function parseDate(string $raw): Carbon
    {
        if ($raw === '') {
            return now();
        }
        if (is_numeric($raw)) {
            // Excel stores dates as days since 30 Dec 1899
            return Carbon::create(1899, 12, 30)->addSeconds((int) round((float) $raw * 86400));
        }
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw);
            } catch (Throwable) {
                // try the next format
            }
        }
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return now();
        }
    }
}