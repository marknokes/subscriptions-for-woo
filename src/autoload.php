<?php

/**
 * Autoloads all required classes.
 *
 * @param string $class_name
 *
 * @return @return void
 */
function ppsfwoo_autoload($class_name)
{
    $namespace = 'PPSFWOO\\';

    if (0 !== strpos($class_name, $namespace)) {
        return;
    }

    $class_name = substr($class_name, strlen($namespace));

    $class_name = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));

    $path = __DIR__.'\classes\\'.$namespace.'class-ppsfwoo-'.$class_name;

    $file = preg_replace('~[\\\\/]~', DIRECTORY_SEPARATOR, $path).'.php';

    if (file_exists($file)) {
        require_once $file;
    }
}
