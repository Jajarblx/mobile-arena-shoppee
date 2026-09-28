<?php

namespace App\Support;

class MobileNumber
{
    public static function normalize(string $value): string
    {
        $number = preg_replace('/[\s().-]+/', '', trim($value));

        if (preg_match('/^09\d{9}$/', $number)) {
            return '+63'.substr($number, 1);
        }

        if (preg_match('/^639\d{9}$/', $number)) {
            return '+'.$number;
        }

        return $number;
    }
}
