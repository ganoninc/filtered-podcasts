<?php

namespace App\Service;

class Slugger
{
    public function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'podcast';
    }
}
