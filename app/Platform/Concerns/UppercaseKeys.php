<?php

declare(strict_types=1);

namespace App\Platform\Concerns;

trait UppercaseKeys
{
    public function toArray()
    {
        $array = parent::toArray();

        return $this->uppercaseKeysRecursive($array);
    }

    private function uppercaseKeysRecursive(array $array): array
    {
        $upperArray = [];
        foreach ($array as $key => $value) {
            $upperKey = is_string($key) ? strtoupper($key) : $key;
            $upperArray[$upperKey] = is_array($value) ? $this->uppercaseKeysRecursive($value) : $value;
        }

        return $upperArray;
    }
}
