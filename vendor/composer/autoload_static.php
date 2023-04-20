<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit19a06bc0ca01d1fede5fbb708d2066cf
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit19a06bc0ca01d1fede5fbb708d2066cf::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit19a06bc0ca01d1fede5fbb708d2066cf::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}