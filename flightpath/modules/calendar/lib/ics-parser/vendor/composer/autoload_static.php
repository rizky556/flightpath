<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit56098203e8acdb483228676e8e1d5854
{
    public static $prefixesPsr0 = array (
        'I' => 
        array (
            'ICal' => 
            array (
                0 => __DIR__ . '/..' . '/johngrogg/ics-parser/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInit56098203e8acdb483228676e8e1d5854::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}