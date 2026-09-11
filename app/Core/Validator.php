<?php

declare(strict_types=1);

namespace App\Core;

use DateTime;

class Validator
{
    public static function make(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $fieldErrors = [];

            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $ruleItem) {
                $param = null;

                if (str_contains($ruleItem, ':')) {
                    [$rule, $param] = explode(':', $ruleItem, 2);
                } else {
                    $rule = $ruleItem;
                }

                if ($rule === 'required' && ($value === null || $value === '')) {
                    $fieldErrors[] = "فیلد {$field} الزامی است";
                    break;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                if ($rule === 'string' && !is_string($value)) {
                    $fieldErrors[] = "فیلد {$field} باید متنی باشد";
                    break;
                }

                if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fieldErrors[] = "فیلد {$field} باید عدد صحیح باشد";
                    break;
                }

                if ($rule === 'boolean' && !in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $fieldErrors[] = "فیلد {$field} باید مقدار بولین باشد";
                    break;
                }

                if ($rule === 'date' && !self::isValidDate((string) $value)) {
                    $fieldErrors[] = "فیلد {$field} باید تاریخ معتبر با فرمت Y-m-d باشد";
                    break;
                }

                if ($rule === 'digits') {
                    if (!is_scalar($value) || !ctype_digit((string) $value) || strlen((string) $value) !== (int) $param) {
                        $fieldErrors[] = "فیلد {$field} باید دقیقاً {$param} رقم باشد";
                        break;
                    }
                }

                if ($rule === 'min' && is_string($value) && mb_strlen($value) < (int) $param) {
                    $fieldErrors[] = "فیلد {$field} باید حداقل {$param} کاراکتر باشد";
                    break;
                }

                if ($rule === 'max' && is_string($value) && mb_strlen($value) > (int) $param) {
                    $fieldErrors[] = "فیلد {$field} باید حداکثر {$param} کاراکتر باشد";
                    break;
                }

                if ($rule === 'in') {
                    $allowedValues = explode(',', (string) $param);

                    if (!in_array($value, $allowedValues, true)) {
                        $fieldErrors[] = "مقدار {$field} معتبر نیست";
                        break;
                    }
                }
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }

        return $errors;
    }

    private static function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value;
    }
}