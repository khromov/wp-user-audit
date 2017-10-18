<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite59292411f2b3925943f8f059b025f08
{
    public static $prefixLengthsPsr4 = array (
        'Z' => 
        array (
            'ZxcvbnPhp\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'ZxcvbnPhp\\' => 
        array (
            0 => __DIR__ . '/..' . '/bjeavons/zxcvbn-php/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite59292411f2b3925943f8f059b025f08::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite59292411f2b3925943f8f059b025f08::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}