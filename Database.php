<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        if(!$query) {
            throw new \Exception('Пустой запрос');
        }

        $iterator = 0;

        $sql = preg_replace_callback(
            '/(?:\{.*(?P<param>\?(?:d|f|a|#|))\.*}|\?(?:d|f|a|#|))/',
            function ($matches) use (&$iterator, $args) {

                if (array_key_exists($iterator, $args)) {

                    $result = match ($matches['param'] ?? $matches[0]) {
                        '?d' => Database::toInt($args[$iterator]),
                        '?f' => Database::toFloat($args[$iterator]),
                        '?a' => Database::toArray($args[$iterator]),
                        '?#' => Database::toList($args[$iterator]),
                        '?' => Database::toAuto($args[$iterator]),
                    };

                if (array_key_exists('param', $matches)) {

                    if($this->skip() === $args[$iterator]) {
                        $result = '';
                    } else {
                        $result = str_replace($matches['param'], $result, $matches[0]);
                        $result = str_replace(['{', '}'], '', $result);
                    }
                }

                } else {

                    throw new \Exception('Не указано значение '.$iterator.' для параметра шаблона ' . $matches[0]);
                }

                $iterator++;

                return $result;

            },
            $query
        );

        return $sql;
    }

    public function skip()
    {
        return NULL;
    }
	
	private static function toInt($data)
    {
        if(is_null($data)) {
            return 'NULL';
        }

        if(is_bool($data)) {
            $data = $data ? 1 : 0;
        }

        if(!is_int($data)) {
            throw new \Exception('Значение '.$data.' не является целым числом');
        }

        return $data;
    }

    private static function toFloat($data)
    {
        if(is_null($data)) {
            return 'NULL';
        }

        if(!is_float($data)) {
            throw new \Exception('Значение '.$data.' не является числом с плавающей запятой');
        }

        return $data;
    }

    private static function toNull()
    {
        return 'NULL';
    }

    private static function toArray($data)
    {
        $is_list = array_is_list($data);

        $str = '';
        $counter = 0;

        foreach ($data as $key => $item) {
            if($counter > 0) {
                $str .= ', ';
            }

            $result = match (gettype($item)) {
                'integer' => Database::toInt($item),
                'double' => Database::toFloat($item),
                'string' => Database::toString($item),
                'NULL' => Database::toNull(),
            };

            if($is_list) {
                $str .= $result;
            } else {
                $str .= '`'.$key.'` = '.$result;
            }
            $counter++;
        }

        return $str;
    }

    private static function toList($data)
    {
        if(is_array($data)) {

            $str = '';
            foreach ($data as $key => $item) {
                if($key > 0) {
                    $str .= ', ';
                }
                $str .= '`'.$item.'`';
            }

        } else {
            $str = '`'.$data.'`';
        }

        return $str;
    }

    private static function toString($data)
    {
        if(is_null($data)) {
            return 'NULL';
        }

        return "'".$data."'";
    }

    private static function toAuto($data)
    {
        return match (gettype($data)) {
            'integer' => Database::toInt($data),
            'double' => Database::toFloat($data),
            'string' => Database::toString($data),
            'array' => Database::toArray($data),
            'NULL' => Database::toNull(),
        };
    }
}
