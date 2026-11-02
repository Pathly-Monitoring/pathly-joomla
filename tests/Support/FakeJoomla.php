<?php

namespace Pathly\Tests;

use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

/**
 * Database answering by query fragment, recording every query.
 */
final class FakeDb implements DatabaseInterface
{
    /** @var array<string, mixed> fragment => result */
    public $results = [];

    public $queries = [];

    public $executed = [];

    private $current = '';

    public function quoteName($name)
    {
        return '`' . $name . '`';
    }

    public function quote($value)
    {
        return "'" . addslashes((string) $value) . "'";
    }

    public function setQuery($sql)
    {
        $this->current   = $sql;
        $this->queries[] = $sql;

        return $this;
    }

    private function result()
    {
        foreach ($this->results as $fragment => $result) {
            if (strpos($this->current, $fragment) !== false) {
                return $result;
            }
        }

        return null;
    }

    public function loadAssoc()
    {
        return $this->result();
    }

    public function loadResult()
    {
        return $this->result();
    }

    public function execute()
    {
        $this->executed[] = $this->current;

        return true;
    }
}

final class FakeInput
{
    public $method = 'POST';

    public $values = [];

    public function getMethod()
    {
        return $this->method;
    }

    public function getCmd($name, $default = null)
    {
        return $this->values[$name] ?? $default;
    }

    public function getInt($name, $default = null)
    {
        return (int) ($this->values[$name] ?? $default);
    }
}

final class FakeSession
{
    public $data = [];

    public $broken = false;

    public function get($key, $default = null)
    {
        if ($this->broken) {
            throw new \RuntimeException('no session');
        }

        return $this->data[$key] ?? $default;
    }

    public function set($key, $value)
    {
        if ($this->broken) {
            throw new \RuntimeException('no session');
        }
        $this->data[$key] = $value;
    }
}

final class FakeUser
{
    public $admin = true;

    public function authorise($action)
    {
        return $action === 'core.admin' && $this->admin;
    }
}

final class FakeLanguage
{
    public $tag = 'fr-FR';

    public function getTag()
    {
        return $this->tag;
    }
}

final class FakeApp
{
    public $client = 'administrator';

    public $identity;

    public $input;

    public $session;

    public $language;

    public $messages = [];

    public $redirects = [];

    public function __construct()
    {
        $this->identity = new FakeUser();
        $this->input    = new FakeInput();
        $this->session  = new FakeSession();
        $this->language = new FakeLanguage();
    }

    public function isClient($name)
    {
        return $name === $this->client;
    }

    public function getIdentity()
    {
        return $this->identity;
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getSession()
    {
        return $this->session;
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function enqueueMessage($message, $type = 'info')
    {
        $this->messages[] = [$type, $message];
    }

    public function redirect($url)
    {
        $this->redirects[] = $url;
    }
}

/**
 * Answer of the Joomla HTTP client (PSR-7 methods).
 */
final class FakeHttpResponse
{
    public $status;

    public $body;

    public $headers;

    public function __construct(int $status, string $body, array $headers = [])
    {
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = $headers;
    }

    public function getStatusCode()
    {
        return $this->status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}

final class FakeHttp
{
    public $calls = [];

    public $response;

    public $throw = false;

    public function __construct()
    {
        $this->response = new FakeHttpResponse(200, '{}', ['Content-Type' => ['application/json']]);
    }

    private function answer(string $method, array $args)
    {
        $this->calls[] = [$method, $args];
        if ($this->throw) {
            throw new \RuntimeException('Could not resolve host https://api.pathlyhq.com');
        }

        return $this->response;
    }

    public function get($url, array $headers = [], $timeout = null)
    {
        return $this->answer('get', [$url, $headers, $timeout]);
    }

    public function post($url, $data, array $headers = [], $timeout = null)
    {
        return $this->answer('post', [$url, $data, $headers, $timeout]);
    }

    public function patch($url, $data, array $headers = [], $timeout = null)
    {
        return $this->answer('patch', [$url, $data, $headers, $timeout]);
    }
}
