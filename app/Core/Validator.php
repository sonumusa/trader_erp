<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Server-side form validation.
 * All validation is performed on the backend — never trusted from the client.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> sanitized output */
    private array $data = [];

    /**
     * Validate $input against rules.
     * Rules: required|email|min:N|max:N|numeric|integer|date|in:a,b,c|confirmed|unique:table,column,ignoreId
     */
    public function validate(array $input, array $rules): bool
    {
        $this->errors = [];
        $this->data = $input;

        foreach ($rules as $field => $ruleString) {
            $rules = array_map('trim', explode('|', $ruleString));
            $value = $input[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
                if (isset($this->errors[$field])) {
                    break;
                }
            }
        }
        return $this->errors === [];
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $label = ucwords(str_replace(['_', '.'], ' ', $field));

        switch ($name) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                    $this->errors[$field] = "{$label} is required.";
                }
                break;

            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = "{$label} must be a valid email address.";
                }
                break;

            case 'min':
                if (is_numeric($value) && (float) $value < (float) $param) {
                    $this->errors[$field] = "{$label} must be at least {$param}.";
                } elseif (is_string($value) && mb_strlen($value) < (int) $param) {
                    $this->errors[$field] = "{$label} must be at least {$param} characters.";
                }
                break;

            case 'max':
                if (is_numeric($value) && (float) $value > (float) $param) {
                    $this->errors[$field] = "{$label} must not exceed {$param}.";
                } elseif (is_string($value) && mb_strlen($value) > (int) $param) {
                    $this->errors[$field] = "{$label} must not exceed {$param} characters.";
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->errors[$field] = "{$label} must be a number.";
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->errors[$field] = "{$label} must be a whole number.";
                }
                break;

            case 'date':
                if ($value !== null && $value !== '' && !self::isValidDate((string) $value)) {
                    $this->errors[$field] = "{$label} must be a valid date (YYYY-MM-DD).";
                }
                break;

            case 'in':
                $allowed = array_map('trim', explode(',', (string) $param));
                if ($value !== null && $value !== '' && !in_array((string) $value, $allowed, true)) {
                    $this->errors[$field] = "{$label} has an invalid value.";
                }
                break;

            case 'confirmed':
                if (isset($this->data[$field . '_confirmation']) && $value !== $this->data[$field . '_confirmation']) {
                    $this->errors[$field] = "{$label} confirmation does not match.";
                }
                break;

            case 'unique':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
                $ignoreId = (int) ($this->data['id'] ?? 0);
                if ($table && $column && $value !== null && $value !== '') {
                    $sql = "SELECT id FROM {$table} WHERE {$column} = ? AND deleted_at IS NULL";
                    $params = [$value];
                    if ($ignoreId > 0) {
                        $sql .= ' AND id != ?';
                        $params[] = $ignoreId;
                    }
                    if (Database::row($sql, $params)) {
                        $this->errors[$field] = "{$label} is already in use.";
                    }
                }
                break;

            case 'strong_password':
                $v = (string) $value;
                if ($v !== '' && (mb_strlen($v) < (int) config('security.password_min_length', 8) || !preg_match('/[A-Za-z]/', $v) || !preg_match('/[0-9]/', $v))) {
                    $this->errors[$field] = "{$label} must be at least " . (int) config('security.password_min_length', 8)
                        . " characters and include letters and numbers.";
                }
                break;
        }
    }

    public static function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
