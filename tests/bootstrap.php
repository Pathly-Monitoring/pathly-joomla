<?php

/**
 * Unit tests bootstrap: Joomla constants, Joomla stand-ins, autoloader.
 */

define('_JEXEC', 1);
define('JVERSION', '5.2.1');
define('JPATH_ADMINISTRATOR', __DIR__);

require __DIR__ . '/stubs/joomla.php';
require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/FakeJoomla.php';
