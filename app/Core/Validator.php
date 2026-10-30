<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validasi server-side. JS di sisi klien hanya pembantu, bukan pengganti.
 *
 * Validator::make($request->all(), [
 *     'email'      => 'required|email|max:160',
 *     'password'   => 'required|min:8',
 *     'queue_date' => 'required|date|date_min:today|date_max:+30 days',
 * ]);
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string> */
    private array $labels;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    private function __construct(array $data, array $rules, array $labels)
    {
        $this->data = $data;
        $this->labels = $labels;

        foreach ($rules as $field => $ruleString) {
            foreach (explode('|', $ruleString) as $rule) {
                if ($this->check($field, $rule) === false) {
                    break; // satu pesan per field sudah cukup
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    public function only(array $fields): array
    {
        $out = [];

        foreach ($fields as $field) {
            $out[$field] = $this->value($field);
        }

        return $out;
    }

    private function value(string $field): mixed
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private function fail(string $field, string $message): bool
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }

        return false;
    }

    private function check(string $field, string $rule): bool
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
        $value = $this->value($field);
        $label = $this->label($field);
        $empty = $value === null || $value === '' || $value === [];

        if ($name === 'required') {
            return $empty ? $this->fail($field, $label . ' wajib diisi.') : true;
        }

        // Aturan lain dilewati bila nilainya kosong dan tidak wajib.
        if ($empty) {
            return true;
        }

        $string = is_scalar($value) ? (string) $value : '';

        switch ($name) {
            case 'email':
                return filter_var($string, FILTER_VALIDATE_EMAIL) === false
                    ? $this->fail($field, $label . ' tidak berformat email yang sah.')
                    : true;

            case 'min':
                return mb_strlen($string) < (int) $param
                    ? $this->fail($field, $label . ' minimal ' . (int) $param . ' karakter.')
                    : true;

            case 'max':
                return mb_strlen($string) > (int) $param
                    ? $this->fail($field, $label . ' maksimal ' . (int) $param . ' karakter.')
                    : true;

            case 'digits':
                return preg_match('/^\d{' . (int) $param . '}$/', $string) !== 1
                    ? $this->fail($field, $label . ' harus ' . (int) $param . ' digit angka.')
                    : true;

            case 'numeric':
                return !is_numeric($string)
                    ? $this->fail($field, $label . ' harus berupa angka.')
                    : true;

            case 'int_min':
                return (int) $string < (int) $param
                    ? $this->fail($field, $label . ' minimal ' . (int) $param . '.')
                    : true;

            case 'phone':
                return preg_match('/^(\+62|62|0)8\d{7,12}$/', preg_replace('/[\s-]/', '', $string) ?? '') !== 1
                    ? $this->fail($field, $label . ' bukan nomor ponsel Indonesia yang sah.')
                    : true;

            case 'in':
                return !in_array($string, explode(',', $param), true)
                    ? $this->fail($field, $label . ' berisi pilihan yang tidak dikenal.')
                    : true;

            case 'same':
                return $string !== (string) ($this->data[$param] ?? '')
                    ? $this->fail($field, $label . ' tidak cocok dengan ' . $this->label($param) . '.')
                    : true;

            case 'date':
                return strtotime($string) === false
                    ? $this->fail($field, $label . ' bukan tanggal yang sah.')
                    : true;

            case 'date_min':
                $min = strtotime($param);
                $ts = strtotime($string);

                return ($ts === false || $min === false || $ts < strtotime(date('Y-m-d', $min)))
                    ? $this->fail($field, $label . ' tidak boleh lebih awal dari ' . date('d-m-Y', (int) $min) . '.')
                    : true;

            case 'date_max':
                $max = strtotime($param);
                $ts = strtotime($string);

                return ($ts === false || $max === false || $ts > strtotime(date('Y-m-d', $max)))
                    ? $this->fail($field, $label . ' tidak boleh lebih dari ' . date('d-m-Y', (int) $max) . '.')
                    : true;

            case 'accepted':
                return !in_array($string, ['1', 'on', 'true', 'yes'], true)
                    ? $this->fail($field, $label . ' harus disetujui.')
                    : true;

            default:
                return true;
        }
    }
}
