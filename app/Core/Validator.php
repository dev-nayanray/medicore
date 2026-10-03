<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reusable input validator.
 *
 * $v = Validator::make($request->all(), [
 *     'email'    => 'required|email|unique:users,email',
 *     'password' => 'required|min:8|confirmed',
 * ]);
 * if ($v->fails()) { ... $v->errors() ... }
 * $clean = $v->validated();
 *
 * Supported rules: required, email, min:x, max:x, numeric, integer,
 * boolean, date, in:a,b,c, confirmed, same:field, unique:table,column[,ignoreId],
 * exists:table,column, regex:/.../
 */
final class Validator
{
    /** @var array<string, array<int, string>> field -> messages */
    private array $errors = [];

    /** @var array<string, mixed> validated (trimmed) input */
    private array $clean = [];

    /** @param array<string, mixed> $data @param array<string, string> $rules */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        $this->run();
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        $this->run();
        return $this->errors;
    }

    /** @return array<string, string> field -> first message (view-friendly) */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors() as $field => $messages) {
            $first[$field] = $messages[0];
        }
        return $first;
    }

    /** @return array<string, mixed> only fields that had rules, trimmed strings */
    public function validated(): array
    {
        $this->run();
        return $this->clean;
    }

    private bool $ran = false;

    private function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        if ($this->rules === []) {
            $this->clean = $this->data;
            return;
        }

        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $isEmpty = $value === null || $value === '' || $value === [];

            // "nullable" short-circuits every rule when the value is absent.
            if (in_array('nullable', $rules, true) && $isEmpty) {
                $this->clean[$field] = null;
                continue;
            }

            $label = self::labelize($field);

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->apply($name, $param, $field, $value, $label, $isEmpty);
                if ($error !== null) {
                    $this->errors[$field][] = $error; // first failing rule wins
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->clean[$field] = $value;
            }
        }
    }

    private function apply(string $name, ?string $param, string $field, mixed $value, string $label, bool $isEmpty): ?string
    {
        switch ($name) {
            case 'required':
                return $isEmpty ? "{$label} is required." : null;

            case 'email':
                return (!$isEmpty && !filter_var((string) $value, FILTER_VALIDATE_EMAIL))
                    ? "{$label} must be a valid email address." : null;

            case 'min':
                $min = (int) $param;
                if ($isEmpty) {
                    return null;
                }
                if (is_numeric($value) && !is_string($value)) {
                    return $value < $min ? "{$label} must be at least {$min}." : null;
                }
                return mb_strlen((string) $value) < $min
                    ? "{$label} must be at least {$min} characters." : null;

            case 'max':
                $max = (int) $param;
                if ($isEmpty) {
                    return null;
                }
                return mb_strlen((string) $value) > $max
                    ? "{$label} may not exceed {$max} characters." : null;

            case 'numeric':
                return (!$isEmpty && !is_numeric($value)) ? "{$label} must be a number." : null;

            case 'integer':
                return (!$isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false)
                    ? "{$label} must be an integer." : null;

            case 'boolean':
                return (!$isEmpty && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on'], true))
                    ? "{$label} must be true or false." : null;

            case 'date':
                return (!$isEmpty && strtotime((string) $value) === false)
                    ? "{$label} must be a valid date." : null;

            case 'in':
                $options = array_map('trim', explode(',', (string) $param));
                return (!$isEmpty && !in_array((string) $value, $options, true))
                    ? "{$label} is invalid." : null;

            case 'confirmed':
                $confirmation = $this->data[$field . '_confirmation'] ?? null;
                return ((string) $value) !== ((string) ($confirmation ?? ''))
                    ? "{$label} confirmation does not match." : null;

            case 'same':
                $other = $this->data[(string) $param] ?? null;
                return ((string) $value) !== ((string) ($other ?? ''))
                    ? "{$label} must match " . self::labelize((string) $param) . "." : null;

            case 'unique':
                [$table, $column, $ignoreId] = array_pad(explode(',', (string) $param), 3, null);
                if ($isEmpty) {
                    return null;
                }
                $column = $column ?: $field;
                $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
                $params = [$value];
                if ($ignoreId !== null && $ignoreId !== '') {
                    $sql .= ' AND id != ?';
                    $params[] = $ignoreId;
                }
                return ((int) Database::scalar($sql, $params)) > 0
                    ? "{$label} is already taken." : null;

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $param), 2, null);
                if ($isEmpty) {
                    return null;
                }
                $column = $column ?: $field;
                return ((int) Database::scalar("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?", [$value])) === 0
                    ? "{$label} was not found." : null;

            case 'regex':
                return (!$isEmpty && !preg_match((string) $param, (string) $value))
                    ? "{$label} format is invalid." : null;

            case 'strong':
                // Password policy: 8+ chars with upper, lower and digit.
                if ($isEmpty) {
                    return null;
                }
                $v = (string) $value;
                if (mb_strlen($v) < 8 || !preg_match('/[A-Z]/', $v) || !preg_match('/[a-z]/', $v) || !preg_match('/\d/', $v)) {
                    return "{$label} must be at least 8 characters and contain an uppercase letter, a lowercase letter and a number.";
                }
                return null;

            default:
                return null; // unknown rules are ignored silently
        }
    }

    private static function labelize(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }
}
