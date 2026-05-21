<?php

namespace Kiss;

use Symfony\Component\Filesystem\Path;

class Tools
{
    public static function slugify($str)
    {
        $str = strtr($str, [
            '<' => '',
            '>' => '',
            '-' => ' ',
            '&' => '',
            '"' => '',
        ]);

        $str = strtr($str, [
            'ый' => 'iy',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH',
            'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ]);

        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

        $str = mb_strtolower($str, 'UTF-8');
        $str = preg_replace('~[^\pL\d.]+~u', '-', $str);
        $str = trim($str, '-');
        $str = preg_replace('~[^-\w.]+~', '', $str);

        return $str;
    }

    public static function truthy($var)
    {
        return in_array(strtolower($var), ['true', '1', 'on', 'y', 'yes']);
    }

    public static function merge($val1, $val2)
    {
        if (!is_array($val1) || !is_array($val2)) {
            return $val2;
        }
        foreach ($val2 as $k => $v) {
            $val1[$k] = self::merge(@$val1[$k], $v);
        }
        return $val1;
    }

    public static function normalizeSource($source, $base = null)
    {
        if (is_null($base)) {
            $base = getcwd();
        }

        // distant file
        if (
            str_starts_with($source, 'http:') ||
            str_starts_with($source, 'https:')
        ) {
            return $source;
        }

        // local file
        return Path::makeAbsolute($source, $base);
    }
}
