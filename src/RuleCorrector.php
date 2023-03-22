<?php
/*
 * Copyright (c) Portland Web Design, Inc 2023.
 */

namespace ahathaway\ValidationRuleGenerator;

/**
 * Class RuleCorrector
 */
class RuleCorrector
{
    /**
     * @param $rules
     * @return array
     */
    public function parseAndCorrect($rules): array
    {
        foreach ($rules as $field => $value) {
            // correct foreign key id columns to have a min value of 1
            if (str_contains($field, '_id')) {
                if (isset($value['exists'])
                    && isset($value['min'])
                    && $value['min'] === 0) {
                    $value['min'] = 1;
                    $rules[$field] = $value;
                }
            }
        }

        return $rules;
    }
}
