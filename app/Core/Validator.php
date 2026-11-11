<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        if (trim($this->string($field)) === '') {
            $this->errors[$field] = 'Informe ' . $label . '.';
        }

        return $this;
    }

    public function email(string $field): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $value = trim($this->string($field));
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] = 'Informe um e-mail válido.';
        }

        return $this;
    }

    public function minLength(string $field, int $min, string $label, bool $trim = true): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $value = $trim ? trim($this->string($field)) : $this->string($field);
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->errors[$field] = $label . ' precisa ter pelo menos ' . $min . ' caracteres.';
        }

        return $this;
    }

    public function maxLength(string $field, int $max, string $label, bool $trim = true): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $value = $trim ? trim($this->string($field)) : $this->string($field);
        if (mb_strlen($value) > $max) {
            $this->errors[$field] = $label . ' pode ter no máximo ' . $max . ' caracteres.';
        }

        return $this;
    }

    public function confirmed(string $field, string $confirmation, string $label): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $left = $this->data[$field] ?? null;
        $right = $this->data[$confirmation] ?? null;
        if (!is_string($left) || !is_string($right) || $left !== $right) {
            $this->errors[$field] = $label . ' não confere.';
        }

        return $this;
    }

    public function pattern(string $field, string $pattern, string $message): self
    {
        if (isset($this->errors[$field])) {
            return $this;
        }

        $value = trim($this->string($field));
        if ($value !== '' && preg_match($pattern, $value) !== 1) {
            $this->errors[$field] = $message;
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function string(string $field): string
    {
        $value = $this->data[$field] ?? '';

        return is_string($value) ? $value : '';
    }
}
