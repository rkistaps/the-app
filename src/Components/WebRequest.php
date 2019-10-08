<?php

namespace TheApp\Components;

use Exception;
use InvalidArgumentException;

/**
 * Class WebRequest
 * @package TheApp\Components
 */
class WebRequest
{
    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';

    const VARIABLE_GET = 'get';
    const VARIABLE_POST = 'post';
    const VARIABLE_REQUEST = 'request';
    const VARIABLE_COOKIE = 'cookie';
    const VARIABLE_SERVER = 'server';
    const VARIABLE_FILES = 'files';
    const VARIABLE_ENV = 'env';

    const VARIABLE_TYPES = [
        self::VARIABLE_GET,
        self::VARIABLE_POST,
        self::VARIABLE_REQUEST,
        self::VARIABLE_COOKIE,
        self::VARIABLE_SERVER,
        self::VARIABLE_FILES,
        self::VARIABLE_ENV,
    ];

    /** @var array */
    private $variables = [];

    /** @var bool Is this a HTTPs request */
    public $isSecure = false;

    /** @var string|null */
    public $method = null;

    /**
     * WebRequest constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Set global variables
     * @param array $get
     * @param array $post
     * @param array $request
     * @param array $server
     * @param array $cookies
     * @param array $files
     * @param array $env
     * @return WebRequest
     * @throws Exception
     */
    public function setGlobals(
        array $get,
        array $post,
        array $request,
        array $server,
        array $cookies,
        array $files,
        array $env
    ) {
        $this->variables = [
            self::VARIABLE_POST => $post,
            self::VARIABLE_GET => $get,
            self::VARIABLE_REQUEST => $request,
            self::VARIABLE_SERVER => $server,
            self::VARIABLE_COOKIE => $cookies,
            self::VARIABLE_FILES => $files,
            self::VARIABLE_ENV => $env,
        ];

        return $this;
    }

    /**
     * Process request variables and process request
     * @return WebRequest
     * @throws Exception
     */
    public function init()
    {
        $this->isSecure = $this->isSecure();
        $this->processRequestMethod();

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function processRequestMethod()
    {
        $this->method = $this->server('REQUEST_METHOD');

        $httpXMethod = $this->server('HTTP_X_HTTP_METHOD');
        if ($this->method === self::METHOD_POST && $httpXMethod) {
            if ($httpXMethod === self::METHOD_DELETE) {
                $this->method = self::METHOD_DELETE;
            } elseif ($httpXMethod === self::METHOD_PUT) {
                $this->method = self::METHOD_PUT;
            } else {
                throw new BadRequestException("Unexpected Header");
            }
        }
    }

    /**
     * Check if request is in given method
     *
     * @param $method
     * @return bool
     */
    public function isMethod($method)
    {
        return $this->method === $method;
    }

    /**
     * Check if its a GET request
     * @return bool
     */
    public function isGet()
    {
        return $this->isMethod(self::METHOD_GET);
    }

    /**
     * Check if its a POST request
     * @return bool
     */
    public function isPost()
    {
        return $this->isMethod(self::METHOD_POST);
    }

    /**
     * Check if its a DELETE request
     * @return bool
     */
    public function isDelete()
    {
        return $this->isMethod(self::METHOD_DELETE);
    }

    /**
     * Check if its a PUT request
     * @return bool
     */
    public function isPut()
    {
        return $this->isMethod(self::METHOD_PUT);
    }

    /**
     * Check if its a HEAD request
     * @return bool
     */
    public function isHead()
    {
        return $this->isMethod(self::METHOD_HEAD);
    }

    /**
     * Check if its a PATCH request
     * @return bool
     */
    public function isPatch()
    {
        return $this->isMethod(self::METHOD_PATCH);
    }

    /**
     * Check if request is secure
     * @return bool
     */
    public function isSecure()
    {
        $https = $this->server('HTTPS', 'off');
        $port = $this->server('SERVER_PORT', 80);

        return $https === 'on' || $port == 443;
    }

    /**
     * @param $type
     * @param null $key
     * @param null $default
     * @return mixed|null
     */
    public function getVariable($type, $key = null, $default = null)
    {
        if (!in_array($type, self::VARIABLE_TYPES)) {
            throw new InvalidArgumentException('Unknown type: ' . $type);
        }

        $data = $this->variables[$type] ?? [];

        if ($key) {
            return $data[$key] ?? $default;
        } else {
            return $data;
        }
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return mixed|null
     */
    public function get(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_GET, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return mixed|null
     */
    public function post(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_POST, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function request(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_REQUEST, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function server(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_SERVER, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return array|mixed|null
     */
    public function cookie(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_COOKIE, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return mixed|null
     */
    public function files(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_FILES, $key, $default);
    }

    /**
     * @param string|null $key
     * @param null $default
     * @return mixed|null
     */
    public function env(string $key = null, $default = null)
    {
        return $this->getVariable(self::VARIABLE_ENV, $key, $default);
    }

    /**
     * Get request url
     * @return string
     */
    public function getUrl()
    {
        $protocol = $this->isSecure ? 'https' : 'http';

        return $protocol . '://' . $this->server('HTTP_HOST') . $this->server('REQUEST_URI');
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->server('REQUEST_URI');
    }
}
