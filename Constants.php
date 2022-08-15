<?php

namespace Neoan3\Apps\Template;

/**
 *
 */
class Constants
{
    /**
     * @var array
     */
    private static array $customAttributes = [];
    /**
     * @var string
     */
    private static string $encoding = 'utf-8';
    /**
     * @var array
     */
    private static array $customFunctions = [];
    /**
     * @var array|string[]
     */
    private static array $delimiter = ['{{','}}'];
    /**
     * @var string
     */
    private static string $path;

    /**
     * @return string
     */
    public static function getPath(): string
    {
        if(!isset(self::$path)){
            self::$path = dirname(__DIR__,3);
        }
        return self::$path;
    }

    /**
     * @return array
     */
    public static function getCustomAttributes(): array
    {
        return self::$customAttributes;
    }

    /**
     * @return string
     */
    public static function getEncoding(): string
    {
        return self::$encoding;
    }

    /**
     * @return array
     */
    public static function getCustomFunctions(): array
    {
        return self::$customFunctions;
    }

    /**
     * @return array
     */
    public static function getDelimiter(): array
    {
        return self::$delimiter;
    }

    public static function delimiterIsTag(): bool
    {
        return preg_match('/^<([^>]+)>$/',self::$delimiter[0]) === 1;
    }


    /**
     * @param string $key
     * @param callable $attributeInstance
     * @return void
     */
    public static function addCustomAttribute(string $key, callable $attributeInstance): void
    {
        self::$customAttributes[$key] = $attributeInstance;
    }

    /**
     * @param string $encoding
     */
    public static function setEncoding(string $encoding): void
    {
        self::$encoding = $encoding;
    }


    /**
     * @param string $key
     * @param callable $customFunction
     * @return void
     */
    public static function addCustomFunction(string $key, callable $customFunction): void
    {
        self::$customFunctions[$key] = $customFunction;
    }


    /**
     * @param string $start
     * @param string $end
     * @return void
     */
    public static function setDelimiter(string $start = '{{', string $end = '}}'): void
    {
        self::$delimiter = [$start, $end];
    }

    /**
     * @param string $path
     * @return void
     */
    public static function setPath(string $path): void
    {
        self::$path = $path;
    }


    /**
     * @param iterable $data
     * @param string|null $parentKey
     * @return array
     */
    public static function flattenArray(iterable $data, string $parentKey = null): array
    {
        $answer = [];
        foreach ($data as $key => $value) {
            if ($parentKey) {
                $key = $parentKey . '.' . $key;
            }
            if (!is_array($value)) {
                $answer[$key] = $value;
            } else {
                $answer[$key] = 'Array';
                $answer[$key.'.length'] = sizeof($value);
                $answer = array_merge($answer, self::flattenArray($value, $key));
            }
        }
        return $answer;
    }
}