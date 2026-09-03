<?php
/**
 * Validator
 * Minimal, dependency-free server-side validation helper.
 *
 * Usage:
 *   $v = new Validator($_POST);
 *   $v->required('first_name', 'First name')
 *     ->maxLength('first_name', 60, 'First name')
 *     ->email('email', 'Email', false);
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $label): self
    {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') {
            $this->errors[$field] = "$label is required.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $val = (string)($this->data[$field] ?? '');
        if (mb_strlen($val) > $max) {
            $this->errors[$field] = "$label must be $max characters or fewer.";
        }
        return $this;
    }

    public function email(string $field, string $label, bool $required = true): self
    {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '' && !$required) return $this;
        if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "$label must be a valid email address.";
        }
        return $this;
    }

    public function date(string $field, string $label, bool $required = true): self
    {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '' && !$required) return $this;
        $d = DateTime::createFromFormat('Y-m-d', $val);
        if (!$d || $d->format('Y-m-d') !== $val) {
            $this->errors[$field] = "$label must be a valid date (YYYY-MM-DD).";
        }
        return $this;
    }

    public function numeric(string $field, string $label, bool $required = true): self
    {
        $val = $this->data[$field] ?? null;
        if (($val === null || $val === '') && !$required) return $this;
        if (!is_numeric($val)) {
            $this->errors[$field] = "$label must be a number.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label, bool $required = true): self
    {
        $val = $this->data[$field] ?? null;
        if (($val === null || $val === '') && !$required) return $this;
        if (!in_array($val, $allowed, true)) {
            $this->errors[$field] = "$label is invalid.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
