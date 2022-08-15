<?php


namespace Neoan3\Apps\Template;


class Template
{

    /**
     * @param string $content
     * @param array $contextData
     *
     * @return string
     */
    static function embrace(string $content, array $contextData): string
    {
        $interpreter = new Interpreter($content, $contextData);
        self::ensureDefaults();
        return $interpreter->asHtml();
    }


    /**
     * @param $location
     * @param $array
     *
     * @return string
     */
    static function embraceFromFile($location, $array): string
    {
        $file = file_get_contents(Constants::getPath() . '/' . $location);
        return self::embrace($file, $array);
    }

    public static function ensureDefaults():void
    {
        // onboard attributes
        $registered = Constants::getCustomAttributes();
        $needed = [
            'n-for' => Attributes\NFor::class,
            'n-if' => Attributes\NIf::class
        ];
        foreach ($needed as $name => $class){
            if(!isset($registered[$name])){
                Constants::addCustomAttribute($name, new $class());
            }
        }

    }

}