<?php

/**
 * Smallest stand-ins for the Joomla classes the extension uses, so the unit
 * tests run without a Joomla site. The integration tests run the real Joomla.
 */

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses, Squiz.Classes.ValidClassName, PSR12.Files.FileHeader

namespace Joomla\Event {
    interface EventInterface
    {
        public function getArgument($name, $default = null);
    }

    class Event implements EventInterface
    {
        public $arguments;

        public function __construct(string $name, array $arguments = [])
        {
            $this->arguments = $arguments;
        }

        public function getArgument($name, $default = null)
        {
            return $this->arguments[$name] ?? $default;
        }
    }

    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }

    interface DispatcherInterface
    {
    }
}

namespace Joomla\DI {
    interface ServiceProviderInterface
    {
        public function register(Container $container);
    }

    class Container
    {
        public $services = [];

        public $providers = [];

        public function set($key, $value)
        {
            $this->services[$key] = $value;

            return $this;
        }

        public function get($key)
        {
            $value = $this->services[$key];

            return $value instanceof \Closure ? $value($this) : $value;
        }

        public function registerServiceProvider(ServiceProviderInterface $provider)
        {
            $this->providers[] = $provider;

            return $this;
        }
    }
}

namespace Joomla\Database {
    interface DatabaseInterface
    {
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static $application;

        public static $container;

        public static function getApplication()
        {
            return self::$application;
        }

        public static function getContainer()
        {
            return self::$container;
        }
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        public static function _($key)
        {
            return $key;
        }

        public static function sprintf($key, ...$args)
        {
            return $key . '|' . implode('|', $args);
        }
    }
}

namespace Joomla\CMS\Session {
    class Session
    {
        public static $valid = true;

        public static $method;

        public static function checkToken($method = 'post')
        {
            self::$method = $method;

            return self::$valid;
        }

        public static function getFormToken()
        {
            return 'tok123';
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static $root = 'https://www.example.test/';

        public static function root()
        {
            return self::$root;
        }
    }
}

namespace Joomla\CMS\Http {
    class HttpFactory
    {
        public static $http;

        public static $options;

        public static function getHttp($options = [], $adapters = null)
        {
            self::$options = $options;

            return self::$http;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function date($input, $format = null)
        {
            return 'DATE(' . $input . ')';
        }
    }
}

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        protected $autoloadLanguage = false;

        public $params;

        public $subject;

        public $application;

        public function __construct(&$subject, $config = [])
        {
            $this->subject = $subject;
            $this->params  = $config['params'] ?? null;
        }

        public function setApplication($application): void
        {
            $this->application = $application;
        }
    }

    class PluginHelper
    {
        public static $enabled = [];

        public static function isEnabled($type, $plugin = null)
        {
            return !empty(self::$enabled[$type . '.' . $plugin]);
        }

        public static function getPlugin($type, $plugin = null)
        {
            return (object) ['type' => $type, 'name' => $plugin, 'params' => '{}'];
        }
    }
}

namespace Joomla\CMS\Extension {
    interface PluginInterface
    {
    }
}

namespace Joomla\CMS\Extension\Service\Provider {
    use Joomla\DI\Container;
    use Joomla\DI\ServiceProviderInterface;

    class ModuleDispatcherFactory implements ServiceProviderInterface
    {
        public $namespace;

        public function __construct($namespace)
        {
            $this->namespace = $namespace;
        }

        public function register(Container $container)
        {
        }
    }

    class Module implements ServiceProviderInterface
    {
        public function register(Container $container)
        {
        }
    }
}

namespace Joomla\CMS\Form {
    abstract class FormField
    {
        protected $type;

        public $name;

        public $id;

        public $value;

        abstract protected function getInput();

        public function input()
        {
            return $this->getInput();
        }
    }
}

namespace Joomla\CMS\Dispatcher {
    abstract class AbstractModuleDispatcher
    {
        protected $module;

        protected $app;

        protected $input;

        public function __construct(\stdClass $module, $app, $input)
        {
            $this->module = $module;
            $this->app    = $app;
            $this->input  = $input;
        }

        protected function getLayoutData()
        {
            return ['module' => $this->module, 'app' => $this->app, 'input' => $this->input, 'params' => null, 'template' => 'atum'];
        }

        public function layoutData(): array
        {
            return $this->getLayoutData();
        }
    }
}
