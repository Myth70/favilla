<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class Validator
{
    private array $errors = [];
    private array $labels = [];

    /**
     * Validate data against rules.
     *
     * @param array $data   Input data ['field' => 'value']
     * @param array $rules  Rules ['field' => 'required|email|min:3|max:255|confirmed|unique:users,email|in:a,b,c|regex:/^\d+$/']
     * @param array $labels Optional human-readable labels ['field' => 'Email'] used in error messages.
     *                      Fields without a label fall back to the field name.
     * @return bool
     */
    public function validate(array $data, array $rules, array $labels = []): bool
    {
        $this->errors = [];
        $this->labels = $labels;

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            // Handle nullable — skip all rules if field is empty
            $isNullable = in_array('nullable', $fieldRules, true);
            if ($isNullable && ($value === null || $value === '' || $value === [])) {
                continue; // Skip to next field
            }

            foreach ($fieldRules as $rule) {
                if ($rule === 'nullable') {
                    continue; // Already handled above
                }

                $param = null;

                if (str_contains($rule, ':')) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                $methodName = 'rule' . ucfirst($rule);

                if (!method_exists($this, $methodName)) {
                    throw new \InvalidArgumentException("Validation rule [{$rule}] does not exist.");
                }
                $this->$methodName($field, $value, $param, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Resolve the human-readable label for a field.
     * Falls back to the raw field name if no label is provided.
     */
    private function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    /**
     * Get validation errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for a field.
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    // ------------------------------------------------------------------
    // Validation rules
    // ------------------------------------------------------------------

    private function ruleRequired(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->addError($field, t('validation.required', ['field' => $this->label($field)]));
        }
    }

    private function ruleEmail(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError($field, t('validation.email', ['field' => $this->label($field)]));
        }
    }

    private function ruleMin(string $field, mixed $value, ?string $param): void
    {
        $min = (int) $param;
        if ($value !== null && $value !== '' && mb_strlen((string) $value) < $min) {
            $this->addError($field, t('validation.min', ['field' => $this->label($field), 'min' => $min]));
        }
    }

    private function ruleMax(string $field, mixed $value, ?string $param): void
    {
        $max = (int) $param;
        if ($value !== null && $value !== '' && mb_strlen((string) $value) > $max) {
            $this->addError($field, t('validation.max', ['field' => $this->label($field), 'max' => $max]));
        }
    }

    private function ruleConfirmed(string $field, mixed $value, ?string $param, array $data): void
    {
        $confirmField = $field . '_confirmation';
        if ($value !== null && $value !== '' && ($data[$confirmField] ?? null) !== $value) {
            $this->addError($field, t('validation.confirmed', ['field' => $this->label($field)]));
        }
    }

    private function ruleUnique(string $field, mixed $value, ?string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // param = "table,column" or "table,column,ignoreId"
        $parts = explode(',', $param);
        $table = $parts[0];
        $column = $parts[1] ?? $field;
        $ignoreId = $parts[2] ?? null;

        try {
            $pdo = app(PDO::class);

            // Whitelist table and column names to prevent SQL injection
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)
                || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                $this->addError($field, t('validation.config_invalid', ['field' => $this->label($field)]));
                return;
            }

            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $params = [$value];

            if ($ignoreId !== null && $ignoreId !== '') {
                $sql .= ' AND id != ?';
                $params[] = (int) $ignoreId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ((int) $stmt->fetchColumn() > 0) {
                $this->addError($field, t('validation.unique', ['field' => $this->label($field)]));
            }
        } catch (\Throwable $e) {
            $this->addError($field, t('validation.unique_check_failed', ['field' => $this->label($field)]));
        }
    }

    private function ruleIn(string $field, mixed $value, ?string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $allowed = explode(',', $param);
        if (!in_array((string) $value, $allowed, true)) {
            $this->addError($field, t('validation.in', ['field' => $this->label($field)]));
        }
    }

    private function ruleRegex(string $field, mixed $value, ?string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $result = @preg_match($param, (string) $value);
        if ($result === false) {
            $this->addError($field, t('validation.regex_invalid', ['field' => $this->label($field)]));
            return;
        }
        if (!$result) {
            $this->addError($field, t('validation.regex', ['field' => $this->label($field)]));
        }
    }

    /**
     * nullable — if the value is empty/null, SKIP all subsequent rules for this field.
     * IMPORTANT: must be the FIRST rule in the chain (e.g. 'nullable|email|max:255')
     */
    private function ruleNullable(string $field, mixed $value): void
    {
        // No errors added — logic is in validate()
    }

    private function ruleNumeric(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, t('validation.numeric', ['field' => $this->label($field)]));
        }
    }

    private function ruleInteger(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, t('validation.integer', ['field' => $this->label($field)]));
        }
    }

    private function ruleUrl(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $this->addError($field, t('validation.url', ['field' => $this->label($field)]));
        }
    }

    private function ruleDate(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && strtotime($value) === false) {
            $this->addError($field, t('validation.date', ['field' => $this->label($field)]));
        }
    }

    private function ruleBefore(string $field, mixed $value, ?string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $limit = strtotime($param);
        $val = strtotime($value);
        if ($limit === false || $val === false || $val >= $limit) {
            $this->addError($field, t('validation.before', ['field' => $this->label($field), 'date' => $param]));
        }
    }

    private function ruleAfter(string $field, mixed $value, ?string $param): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $limit = strtotime($param);
        $val = strtotime($value);
        if ($limit === false || $val === false || $val <= $limit) {
            $this->addError($field, t('validation.after', ['field' => $this->label($field), 'date' => $param]));
        }
    }

    // ------------------------------------------------------------------

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
