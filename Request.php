<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kaa\HttpFoundation;

use Kaa\HttpFoundation\Exception\ConflictingHeadersException;
use Kaa\HttpFoundation\Exception\BadRequestException;
use Kaa\HttpFoundation\Exception\JsonException;
use Kaa\HttpFoundation\Exception\SuspiciousOperationException;
use InvalidArgumentException;

/**
 * This file has been rewritten for KPHP compilation.
 * Please refer to the original Symfony HttpFoundation repository for the original source code.
 * @see https://github.com/symfony/http-foundation
 * @author Mikhail Fedosov <fedosovmichael@gmail.com>
 *
 * Request represents an HTTP request.
 *
 * The methods dealing with URL accept / return a raw path (% encoded):
 *   * getBasePath
 *   * getBaseUrl
 *   * getPathInfo
 *   * getRequestUri
 *   * getUri
 *   * getUriForPath
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Request
{
    # In KPHP, there is not yet a predefined constant directory_separator
    public const DIRECTORY_SEPARATOR = "/";

    public const HEADER_FORWARDED = 0b000001; // When using RFC 7239
    public const HEADER_X_FORWARDED_FOR = 0b000010;
    public const HEADER_X_FORWARDED_HOST = 0b000100;
    public const HEADER_X_FORWARDED_PROTO = 0b001000;
    public const HEADER_X_FORWARDED_PORT = 0b010000;
    public const HEADER_X_FORWARDED_PREFIX = 0b100000;

    public const HEADER_X_FORWARDED_AWS_ELB = 0b0011010; // AWS ELB doesn't send X-Forwarded-Host
    public const HEADER_X_FORWARDED_TRAEFIK = 0b0111110; // All "X-Forwarded-*" headers sent by Traefik reverse proxy

    public const METHOD_HEAD = 'HEAD';
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_PURGE = 'PURGE';
    public const METHOD_OPTIONS = 'OPTIONS';
    public const METHOD_TRACE = 'TRACE';
    public const METHOD_CONNECT = 'CONNECT';

    /** @var string[] */
    protected static $trustedProxies = [];

    /** @var string[] */
    protected static $trustedHostPatterns = [];

    /** @var string[] */
    protected static $trustedHosts = [];

    /** @var string[][] */
    private static $formats = [
            'html' => ['text/html', 'application/xhtml+xml'],
            'txt' => ['text/plain'],
            'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'],
            'css' => ['text/css'],
            'json' => ['application/json', 'application/x-json'],
            'jsonld' => ['application/ld+json'],
            'xml' => ['text/xml', 'application/xml', 'application/x-xml'],
            'rdf' => ['application/rdf+xml'],
            'atom' => ['application/atom+xml'],
            'rss' => ['application/rss+xml'],
            'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
        ];

    private static $httpMethodParameterOverride = false;

    /**
     * Custom parameters.
     */
    public ParameterBag $attributes;

    /**
     * Request body parameters ($_POST).
     */
    public InputBag $request;

    /**
     * Query string parameters ($_GET).
     */
    public InputBag $query;

    /**
     * Server and execution environment parameters ($_SERVER).
     */
    public ServerBag $server;

    /**
     * Uploaded files ($_FILES).
     */
    public FileBag $files;

    /**
     * Cookies ($_COOKIE).
     */
    public InputBag $cookies;

    /**
     * Headers (taken from the $_SERVER).
     */
    public HeaderBag $headers;

    private ?string $content;

    /** @var ?string[] */
    private $languages;

    /** @var ?string[] */
    private $charsets;

    /** @var ?string[] */
    private $encodings;

    /** @var ?string[] */
    private $acceptableContentTypes;

    private ?string $pathInfo;

    private ?string $requestUri;

    private ?string $baseUrl;

    private ?string $basePath;

    private ?string $method;

    private ?string $format;

//    private ?Session $session = null;
    private ?string $preferredFormat = null;

    private bool $isHostValid = true;

    private bool $isForwardedValid = true;

    private ?bool $isSafeContentPreferred = null;

    private static int $trustedHeaderSet = -1;

    private const FORWARDED_PARAMS = [
        self::HEADER_X_FORWARDED_FOR => 'for',
        self::HEADER_X_FORWARDED_HOST => 'host',
        self::HEADER_X_FORWARDED_PROTO => 'proto',
        self::HEADER_X_FORWARDED_PORT => 'host',
    ];

    /**
     * Names for headers that can be trusted when
     * using trusted proxies.
     *
     * The FORWARDED header is the standard as of rfc7239.
     *
     * The other headers are non-standard, but widely used
     * by popular reverse proxies (like Apache mod_proxy or Amazon EC2).
     */
    private const TRUSTED_HEADERS = [
        self::HEADER_FORWARDED => 'FORWARDED',
        self::HEADER_X_FORWARDED_FOR => 'X_FORWARDED_FOR',
        self::HEADER_X_FORWARDED_HOST => 'X_FORWARDED_HOST',
        self::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
        self::HEADER_X_FORWARDED_PORT => 'X_FORWARDED_PORT',
        self::HEADER_X_FORWARDED_PREFIX => 'X_FORWARDED_PREFIX',
    ];

    /**
     * @param string[]             $query      The GET parameters
     * @param string[]             $request    The POST parameters
     * @param string[]             $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param string[]             $cookies    The COOKIE parameters
     * @param string[]             $files      The FILES parameters
     * @param string[]             $server     The SERVER parameters
     * @param string|null|false    $content    The raw body data.
     * The type false is forced, because $contnent can pass the result of json_encode,
     * but in KPHP it returns string|false. Then false will be replaced by null
     */
    public function __construct(
        $query = [],
        $request = [],
        $attributes = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null
    ) {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }

    /**
     * Sets the parameters for this request.
     *
     * This method also re-initializes all properties.
     *
     * @param string[]             $query      The GET parameters
     * @param string[]             $request    The POST parameters
     * @param string[]             $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param string[]             $cookies    The COOKIE parameters
     * @param string[]             $files      The FILES parameters
     * @param string[]             $server     The SERVER parameters
     * @param string|null|false    $content    The raw body data
     */
    public function initialize(
        $query = [],
        $request = [],
        $attributes = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null
    ): void {
        $this->query = new InputBag($query);
        $this->request = new InputBag($request);
        $this->attributes = new ParameterBag($attributes);
        $this->cookies = new InputBag($cookies);
        $this->files = new FileBag($files);
        $this->server = new ServerBag($server);
        $this->headers = new HeaderBag($this->server->getHeaders());

        if ($content === false) {
            $this->content = null;
        } else {
            $this->content = $content;
        }

        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
    }

    /**
     * Creates a new request with values from PHP's super globals.
     */
    public static function createFromGlobals(): static
    {
        /** @var string[] $getArray */
        $getArray = array_map('strval', $_GET);

        /** @var string[] $postArray */
        $postArray = array_map('strval', $_POST);

        /** @var string[] $cookiesArray */
        $cookiesArray = array_map('strval', $_COOKIE);

        /** @var mixed $filesStringValues */
        $filesStringValues = array_filter($_FILES, function ($value) {
            return !\is_array($value);
        });

        /** @var string[] $filesArray */
        $filesArray = array_map('strval', $filesStringValues);

        /** @var mixed $serverStringValues */
        $serverStringValues = array_filter($_SERVER, function ($value) {
            return !\is_array($value);
        });

        /** @var string[] $serverArray */
        $serverArray = array_map('strval', $serverStringValues);

        $request = new static($getArray, $postArray, [], $cookiesArray, [], $serverArray, null);

        $request->files = new FileBag($filesArray);

        $headerString = $request->headers->get('CONTENT_TYPE', '');

        if (
            isset($headerString) && str_starts_with($headerString, 'application/x-www-form-urlencoded')
            && \in_array(
                strtoupper((string)$request->server->get('REQUEST_METHOD', 'GET')),
                ['PUT', 'DELETE', 'PATCH'],
                true
            )
        ) {
            parse_str((string)$request->getContent(), $data);
            $request->request = new InputBag($data);
        }

        return $request;
    }

    /**
     * Creates a Request based on a given URI and configuration.
     *
     * The information contained in the URI always take precedence
     * over the other information (server and parameters).
     *
     * @param string               $uri        The URI
     * @param string               $method     The HTTP method
     * @param string[]             $parameters The query (GET) or request (POST) parameters
     * @param string[]             $cookies    The request cookies ($_COOKIE)
     * @param string[]             $files      The request files ($_FILES)
     * @param string[]             $server     The server parameters ($_SERVER)
     * @param ?string              $content    The raw body data
     */
    public static function create(
        $uri,
        $method = 'GET',
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = [],
        $content = null
    ): self {
        # It does not make sense to put the $server variable as a ServerConfig class.
        # Let's just convert everything to a string[] array
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'Symfony',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '',
            'SCRIPT_FILENAME' => '',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => (string)time(),
            'REQUEST_TIME_FLOAT' => (string)microtime(true),
        ], $server);

        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = strtoupper($method);

        $components = parse_url($uri);

        if (is_array($components)) {
            $components = array_map('strval', $components);
        } else {
            $components = [];
        }

        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }

        if (isset($components['scheme'])) {
            if ($components['scheme'] === 'https') {
                $server['HTTPS'] = 'on';
                $server['SERVER_PORT'] = '443';
            } else {
                unset($server['HTTPS']);
                $server['SERVER_PORT'] = '80';
            }
        }

        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] .= ':' . $components['port'];
        }

        if (isset($components['user'])) {
            $server['PHP_AUTH_USER'] = $components['user'];
        }

        if (isset($components['pass'])) {
            $server['PHP_AUTH_PW'] = $components['pass'];
        }

        if (!isset($components['path'])) {
            $components['path'] = '/';
        }

        switch (strtoupper($method)) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
                if (!isset($server['CONTENT_TYPE'])) {
                    $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
                }
            // no break
            case 'PATCH':
                $request = $parameters;
                $query = [];
                break;
            default:
                $request = [];
                $query = $parameters;
                break;
        }

        $queryString = '';
        if (isset($components['query'])) {
            parse_str(html_entity_decode($components['query']), $qs);

            $qs = array_map('strval', $qs);

            if ((bool)$query) {
                $query = array_replace($qs, $query);
                $queryString = http_build_query($query, '', '&');
            } else {
                $query = $qs;
                $queryString = $components['query'];
            }
        } elseif ((bool)$query) {
            $queryString = http_build_query($query, '', '&');
        }

        $server['REQUEST_URI'] = $components['path'] . ((string)$queryString !== '' ? '?' . $queryString : '');
        $server['QUERY_STRING'] = $queryString;

        return new static($query, $request, [], $cookies, $files, $server, $content);
    }

    /**
     * Clones a request and overrides some of its parameters.
     *
     * @param ?string[] $query      The GET parameters
     * @param ?string[] $request    The POST parameters
     * @param ?string[] $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param ?string[] $cookies    The COOKIE parameters
     * @param ?string[] $files      The FILES parameters
     * @param ?string[] $server     The SERVER parameters
     */
    public function duplicate(
        $query = null,
        $request = null,
        $attributes = null,
        $cookies = null,
        $files = null,
        $server = null
    ): static {
        $dup = clone $this;
        if ($query !== null) {
            $dup->query = new InputBag($query);
        }
        if ($request !== null) {
            $dup->request = new InputBag($request);
        }
        if ($attributes !== null) {
            $dup->attributes = new ParameterBag($attributes);
        }
        if ($cookies !== null) {
            $dup->cookies = new InputBag($cookies);
        }
        if ($files !== null) {
            $dup->files = new FileBag($files);
        }
        if ($server !== null) {
            $dup->server = new ServerBag($server);
            $dup->headers = new HeaderBag($dup->server->getHeaders());
        }
        $dup->languages = null;
        $dup->charsets = null;
        $dup->encodings = null;
        $dup->acceptableContentTypes = null;
        $dup->pathInfo = null;
        $dup->requestUri = null;
        $dup->baseUrl = null;
        $dup->basePath = null;
        $dup->method = null;
        $dup->format = null;

        if (!(bool)$dup->get('_format') && (bool)$this->get('_format')) {
            $dup->attributes->set('_format', (string)$this->get('_format'));
        }

        if (!(bool)$dup->getRequestFormat(null)) {
            $dup->setRequestFormat($this->getRequestFormat(null));
        }

        return $dup;
    }

    /**
     * Clones the current request.
     *
     * Note that the session is not cloned as duplicated requests
     * are most of the time sub-requests of the main one.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        $this->request = clone $this->request;
        $this->attributes = clone $this->attributes;
        $this->cookies = clone $this->cookies;
        $this->files = clone $this->files;
        $this->server = clone $this->server;
        $this->headers = clone $this->headers;
    }

    public function __toString(): string
    {
        $content = $this->getContent();

        $cookieHeader = '';
        $cookies = [];

        foreach ($this->cookies->all() as $k => $v) {
            if (\is_array($v)) {
                $cookies[] = http_build_query([$k => $v], '', '; ', \PHP_QUERY_RFC3986);
            } else {
                $key = (string)$k;
                $value = (string)$v;
                $cookies[] = "{$key}={$value}";
            }
        }

        if (count($cookies) !== 0) {
            $cookieHeader = 'Cookie: ' . implode('; ', $cookies) . "\r\n";
        }

        return
            sprintf(
                '%s %s %s',
                $this->getMethod(),
                $this->getRequestUri(),
                $this->server->get('SERVER_PROTOCOL')
            ) .
            "\r\n" .
            (string)$this->headers .
            $cookieHeader . "\r\n" .
            $content;
    }

    /**
     * Overrides the PHP global variables according to this request instance.
     *
     * It overrides $_GET, $_POST, $_REQUEST, $_SERVER, $_COOKIE.
     * $_FILES is never overridden, see rfc1867
     */
    public function overrideGlobals(): void
    {
        $this->server->set(
            'QUERY_STRING',
            self::normalizeQueryString(http_build_query($this->query->all(), '', '&'))
        );

        $_GET = $this->query->all();
        $_POST = $this->request->all();
        $_SERVER = $this->server->all();
        $_COOKIE = $this->cookies->all();

        if (count($this->headers->all()) !== 0) {
            foreach ($this->headers->all() as $key => $value) {
                $count = 0;
                $key = strtoupper(str_replace('-', '_', $key, $count));
                if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $_SERVER[$key] = implode(', ', $value);
                } else {
                    $_SERVER['HTTP_' . $key] = implode(', ', $value);
                }
            }
        }

        $request = ['g' => $_GET, 'p' => $_POST, 'c' => $_COOKIE];

        $requestOrder = 'gpc';

        $_REQUEST = [[]];

        foreach (str_split($requestOrder) as $order) {
            $_REQUEST[] = $request[$order];
        }

        $requestArray = [];
        foreach ($_REQUEST as $requestData) {
            if (is_array($requestData)) {
                $requestArray = array_merge($requestArray, $requestData);
            }
        }
        $_REQUEST = $requestArray;
    }

    /**
     * Sets a list of trusted proxies.
     *
     * You should only list the reverse proxies that you manage directly.
     *
     * @param ?string[] $proxies
     * A list of trusted proxies, the string 'REMOTE_ADDR' will be replaced with $_SERVER['REMOTE_ADDR']
     *
     * @param int       $trustedHeaderSet
     * A bit field of Request::HEADER_*, to set which headers to trust from your proxies
     */
    public static function setTrustedProxies($proxies, int $trustedHeaderSet): void
    {
        if ($proxies === null) {
            self::$trustedProxies = [];
            self::$trustedHeaderSet = $trustedHeaderSet;
            return;
        }

        self::$trustedProxies = array_reduce($proxies, static function ($proxies, $proxy) {
            if ($proxy !== 'REMOTE_ADDR') {
                $proxies[] = (string)$proxy;
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $proxies[] = (string)$_SERVER['REMOTE_ADDR'];
            }

            return $proxies;
        }, []);
        self::$trustedHeaderSet = $trustedHeaderSet;
    }

    /**
     * Gets the list of trusted proxies.
     *
     * @return string[]
     */
    public static function getTrustedProxies()
    {
        return self::$trustedProxies;
    }

    /**
     * Gets the set of trusted headers from trusted proxies.
     *
     * @return int A bit field of Request::HEADER_* that defines which headers are trusted from your proxies
     */
    public static function getTrustedHeaderSet(): int
    {
        return self::$trustedHeaderSet;
    }

    /**
     * Sets a list of trusted host patterns.
     *
     * You should only list the hosts you manage using regexs.
     *
     * @param string[] $hostPatterns A list of trusted host patterns
     */
    public static function setTrustedHosts($hostPatterns)
    {
        self::$trustedHostPatterns = array_map(function ($hostPattern) {
            return sprintf('{%s}i', $hostPattern);
        }, $hostPatterns);
        // we need to reset trusted hosts on trusted host patterns change
        self::$trustedHosts = [];
    }

    /**
     * Gets the list of trusted host patterns.
     *
     * @return string[]
     */
    public static function getTrustedHosts()
    {
        return self::$trustedHostPatterns;
    }

    /**
     * Normalizes a query string.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized,
     * have consistent escaping and unneeded delimiters are removed.
     */
    public static function normalizeQueryString(?string $qs): string
    {
        if (($qs ?? '') === '') {
            return '';
        }

        $qs = HeaderUtils::parseQuery((string)$qs);
        ksort($qs);

        return http_build_query($qs, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Enables support for the _method request parameter to determine the intended HTTP method.
     *
     * Be warned that enabling this feature might lead to CSRF issues in your code.
     * Check that you are using CSRF tokens when required.
     * If the HTTP method parameter override is enabled, an html-form with method "POST" can be altered
     * and used to send a "PUT" or "DELETE" request via the _method request parameter.
     * If these methods are not protected against CSRF, this presents a possible vulnerability.
     *
     * The HTTP method can only be overridden when the real HTTP method is POST.
     */
    public static function enableHttpMethodParameterOverride(): void
    {
        self::$httpMethodParameterOverride = true;
    }

    /**
     *   This feature is not in Symfony/HttpFoundation, but it is used in some tests and is implemented like this:
     *
     *   private function disableHttpMethodParameterOverride()
     *   {
     *       $class = new \ReflectionClass(Request::class);
     *       $property = $class->getProperty('httpMethodParameterOverride');
     *       $property->setValue(false);
     *   }
     */
    public static function disableHttpMethodParameterOverride(): void
    {
        self::$httpMethodParameterOverride = false;
    }

    /**
     * Checks whether support for the _method request parameter is enabled.
     */
    public static function getHttpMethodParameterOverride(): bool
    {
        return self::$httpMethodParameterOverride;
    }

    /**
     * Gets a "parameter" value from any bag.
     *
     * This method is mainly useful for libraries that want to provide some flexibility. If you don't need the
     * flexibility in controllers, it is better to explicitly get request parameters from the appropriate
     * public property instead (attributes, query, request).
     *
     * Order of precedence: PATH (routing placeholders or custom attributes), GET, POST
     *
     *@internal use explicit input sources instead
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $result = $this->attributes->get($key, (string)$this);
        if ((string)$this !== $result) {
            return $result;
        }

        $qAll = $this->query->all();
        if ($this->query->has($key) && \is_array($qAll)) {
            return (string)$qAll[$key];
        }

        $rAll = $this->request->all();
        if ($this->request->has($key) && \is_array($rAll)) {
            return (string)$rAll[$key];
        }

        return $default;
    }

////
////    /**
////     * Gets the Session.
////     *
////     * @throws SessionNotFoundException When session is not set properly
////     */
////    public function getSession(): SessionInterface
////    {
////        $session = $this->session;
////        if (!$session instanceof SessionInterface && null !== $session) {
////            $this->setSession($session = $session());
////        }
////
////        if (null === $session) {
////            throw new SessionNotFoundException('Session has not been set.');
////        }
////
////        return $session;
////    }
////
////    /**
////     * Whether the request contains a Session which was started in one of the
////     * previous requests.
////     */
////    public function hasPreviousSession(): bool
////    {
////        // the check for $this->session avoids malicious users trying to fake a session cookie with proper name
////        return $this->hasSession() && $this->cookies->has($this->getSession()->getName());
////    }

//    /**
//     * Whether the request contains a Session object.
//     *
//     * This method does not give any information about the state of the session object,
//     * like whether the session is started or not. It is just a way to check if this Request
//     * is associated with a Session instance.
//     *
//     * @param bool $skipIfUninitialized When true, ignores factories injected by `setSessionFactory`
//     */
//    public function hasSession(bool $skipIfUninitialized = false): bool
//    {
//        return $this->session !== null && !$skipIfUninitialized;
//    }

//    public function setSession(Session $session): void
//    {
//        $this->session = $session;
//    }

    /**
     * Returns the client IP addresses.
     *
     * In the returned array the most trusted IP address is first, and the
     * least trusted one last. The "real" client IP address is the last one,
     * but this is also the least trusted one. Trusted proxies are stripped.
     *
     * Use this method carefully; you should use getClientIp() instead.
     *
     * @see getClientIp()
     * @return (?string)[]
     */
    public function getClientIps()
    {
        $ip = $this->server->get('REMOTE_ADDR');

        if (!$this->isFromTrustedProxy()) {
            return [$ip];
        }

        return $this->getTrustedValues(self::HEADER_X_FORWARDED_FOR, $ip) ?: [$ip];
    }

    /**
     * Returns the client IP address.
     *
     * This method can read the client IP address from the "X-Forwarded-For" header
     * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
     * header value is a comma+space separated list of IP addresses, the left-most
     * being the original client, and each successive proxy that passed the request
     * adding the IP address where it received the request from.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-For",
     * ("Client-Ip" for instance), configure it via the $trustedHeaderSet
     * argument of the Request::setTrustedProxies() method instead.
     *
     * @see getClientIps()
     * @see https://wikipedia.org/wiki/X-Forwarded-For
     */
    public function getClientIp(): ?string
    {
        $ipAddresses = $this->getClientIps();

        return $ipAddresses[0];
    }

    /**
     * Returns current script name.
     */
    public function getScriptName(): string
    {
        return (string)$this->server->get('SCRIPT_NAME', (string)$this->server->get('ORIG_SCRIPT_NAME', ''));
    }

    /**
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @return ?string The raw path (i.e. not urldecoded)
     */
    public function getPathInfo(): ?string
    {
        return $this->pathInfo ??= $this->preparePathInfo();
    }

    /**
     * Returns the root path from which this request is executed.
     *
     * Suppose that an index.php file instantiates this request object:
     *
     *  * http://localhost/index.php         returns an empty string
     *  * http://localhost/index.php/page    returns an empty string
     *  * http://localhost/web/index.php     returns '/web'
     *  * http://localhost/we%20b/index.php  returns '/we%20b'
     *
     * @return ?string The raw path (i.e. not urldecoded)
     */
    public function getBasePath()
    {
        return $this->basePath ??= $this->prepareBasePath();
    }

    /**
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    public function getBaseUrl(): string
    {
        $trustedPrefix = '';

        // the proxy prefix must be prepended to any prefix being needed at the webserver level
        $trustedPrefixValues = $this->getTrustedValues(self::HEADER_X_FORWARDED_PREFIX);
        if ($this->isFromTrustedProxy() && (count($trustedPrefixValues) !== 0)) {
            $trustedPrefix = rtrim($trustedPrefixValues[0], '/');
        }

        return $trustedPrefix . $this->getBaseUrlReal();
    }

    /**
     * Returns the real base URL received by the webserver from which this request is executed.
     * The URL does not include trusted reverse proxy prefix.
     *
     * @return ?string The raw URL (i.e. not urldecoded)
     */
    private function getBaseUrlReal(): ?string
    {
        return $this->baseUrl ??= $this->prepareBaseUrl();
    }

    /**
     * Gets the request's scheme.
     */
    public function getScheme(): string
    {
        if ($this->isSecure()) {
            return 'https';
        }
        return 'http';
    }

    /**
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Port" header must contain the client port.
     *
     * @return int Can be a string if fetched from the server bag
     */
    public function getPort(): int
    {
        $hostTrustedPort = $this->getTrustedValues(self::HEADER_X_FORWARDED_PORT);
        $hostTrustedHost = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST);
        $hostHeadersGet = $this->headers->get('HOST');

        if ($this->isFromTrustedProxy() && (count($hostTrustedPort) !== 0)) {
            $host = $hostTrustedPort[0];
        } elseif ($this->isFromTrustedProxy() && (count($hostTrustedHost) !== 0)) {
            $host = $hostTrustedHost[0];
        } elseif ($hostHeadersGet  === null) {
            return (int)$this->server->get('SERVER_PORT');
        } else {
            $host = $hostHeadersGet;
        }

        if ($host[0] === '[') {
            $pos = strpos($host, ':', (int)strrpos($host, ']'));
        } else {
            $pos = strrpos($host, ':');
        }

        $port = substr($host, (int)$pos + 1);

        if ($pos !== false && (bool)$port) {
            return (int)$port;
        }

        if ($this->getScheme() === 'https') {
            return 443;
        }
        return 80;
    }

    /**
     * Returns the user.
     */
    public function getUser(): ?string
    {
        return $this->headers->get('PHP_AUTH_USER');
    }

    /**
     * Returns the password.
     */
    public function getPassword(): string
    {
        return (string)$this->headers->get('PHP_AUTH_PW');
    }

    /**
     * Gets the user info.
     *
     * @return ?string A user name if any and, optionally,
     * scheme-specific information about how to gain authorization to access the server
     */
    public function getUserInfo(): ?string
    {
        $userinfo = $this->getUser();

        $pass = $this->getPassword();
        if ($pass != '') {
            $userinfo .= ":{$pass}";
        }

        return $userinfo;
    }

    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     */
    public function getHttpHost(): string
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();

        if (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443)) {
            return $this->getHost();
        }

        if ($port == 80) {
            return $this->getHost();
        }

        return $this->getHost() . ':' . $port;
    }

    /**
     * Returns the requested URI (path and query string).
     *
     * @return ?string The raw URI (i.e. not URI decoded)
     */
    public function getRequestUri(): ?string
    {
        return $this->requestUri ??= $this->prepareRequestUri();
    }

    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     */
    public function getSchemeAndHttpHost(): string
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }

    /**
     * Generates a normalized URI (URL) for the Request.
     *
     * @see getQueryString()
     */
    public function getUri(): string
    {
        $qs = $this->getQueryString();
        if ($qs !== null) {
            $qs = '?' . $qs;
        }

        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $this->getPathInfo() . $qs;
    }

    /**
     * Generates a normalized URI for the given path.
     *
     * @param string $path A path to use instead of the current one
     */
    public function getUriForPath(string $path): string
    {
        return $this->getSchemeAndHttpHost() . $this->getBaseUrl() . $path;
    }

    /**
     * Returns the path as relative reference from the current Request path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained
     * downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     */
    public function getRelativeUriForPath(string $path): string
    {
        // be sure that we are dealing with an absolute path
        if (strlen($path) === 0 || $path[0] !== '/') {
            return $path;
        }

        $basePath = $this->getPathInfo();
        if ($path === $basePath) {
            return '';
        }

        if (isset($basePath) && $basePath[0] === '/') {
            $sourceDirs = explode('/', substr($basePath, 1));
        } else {
            $sourceDirs = explode('/', (string)$basePath);
        }
        $targetDirs = explode('/', substr($path, 1));
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', \count($sourceDirs)) . implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see https://tools.ietf.org/html/rfc3986#section-4.2).
        if (
            $path[0] === '/' || ($colonPos = strpos($path, ':')) !== false
            && ($colonPos < ($slashPos = strpos($path, '/')) || $slashPos === false)
        ) {
            return "./{$path}";
        }
        return $path;
    }

    /**
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     */
    public function getQueryString(): ?string
    {
        $qs = self::normalizeQueryString($this->server->get('QUERY_STRING'));

        if ($qs === '') {
            return null;
        }
        return $qs;
    }

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     */
    public function isSecure(): bool
    {
        $proto = $this->getTrustedValues(self::HEADER_X_FORWARDED_PROTO);
        if ($this->isFromTrustedProxy() && (count($proto) !== 0)) {
            return \in_array(strtolower($proto[0]), ['https', 'on', 'ssl', '1'], true);
        }

        $https = $this->server->get('HTTPS');

        return !empty($https) && strtolower($https) !== 'off';
    }

    /**
     * Returns the host name.
     *
     * This method can read the client host name from the "X-Forwarded-Host" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Host" header must contain the client host name.
     *
     * @throws SuspiciousOperationException when the host name is invalid or not trusted
     */
    public function getHost(): string
    {
        $trustedValues = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST);

        if ($this->isFromTrustedProxy() && (count($trustedValues) !== 0)) {
            $host = (string)$trustedValues[0];
        } else {
            $host = $this->headers->get('HOST');
            if ($host === null) {
                $host = $this->server->get('SERVER_NAME');
                if ($host === null) {
                    $host = $this->server->get('SERVER_ADDR', '');
                }
            }
        }


        // trim and remove port number from host
        // host is lowercase as per RFC 952/2181
        $host = strtolower((string)preg_replace('/:\d+$/', '', trim((string)$host)));

        // as the host can come from the user (HTTP_HOST and depending on the configuration,
        // SERVER_NAME too can come from the user)
        // check that it does not contain forbidden characters (see RFC 952 and RFC 2181)
        // use preg_replace() instead of preg_match() to prevent DoS attacks with long host names
        if ((bool)$host && preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host) !== '') {
            if (!$this->isHostValid) {
                return '';
            }
            $this->isHostValid = false;

            throw new SuspiciousOperationException(sprintf('Invalid Host "%s".', $host));
        }

        if (\count(self::$trustedHostPatterns) > 0) {
            // to avoid host header injection attacks, you should provide a list of trusted host patterns

            if (\in_array($host, self::$trustedHosts, true)) {
                return $host;
            }

            foreach (self::$trustedHostPatterns as $pattern) {
                if ((bool)preg_match($pattern, $host, $matches)) {
                    self::$trustedHosts[] = $host;

                    return $host;
                }
            }

            if (!$this->isHostValid) {
                return '';
            }
            $this->isHostValid = false;

            throw new SuspiciousOperationException(sprintf('Untrusted Host "%s".', $host));
        }

        return $host;
    }

    /**
     * Sets the request method.
     */
    public function setMethod(string $method): void
    {
        $this->method = null;
        $this->server->set('REQUEST_METHOD', $method);
    }

    /**
     * Gets the request "intended" method.
     *
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     *
     * The _method request parameter can also be used to determine the HTTP method,
     * but only if enableHttpMethodParameterOverride() has been called.
     *
     * The method is always an uppercased string.
     *
     * @throws SuspiciousOperationException
     * @throws BadRequestException
     * @throws InvalidArgumentException
     *
     * @see getRealMethod()
     */
    public function getMethod(): ?string
    {
        if ($this->method !== null) {
            return $this->method;
        }

        $this->method = strtoupper((string)$this->server->get('REQUEST_METHOD', 'GET'));

        if ($this->method !== 'POST') {
            return $this->method;
        }

        $method = $this->headers->get('X-HTTP-METHOD-OVERRIDE');

        if (!(bool)$method && self::$httpMethodParameterOverride) {
            $method = $this->request->get('_method', (string)$this->query->get('_method', 'POST'));
        }

        if (!\is_string($method)) {
            return $this->method;
        }

        $method = strtoupper($method);

        if (
            \in_array(
                $method,
                ['GET', 'HEAD', 'POST',
                'PUT', 'DELETE', 'CONNECT',
                'OPTIONS', 'PATCH', 'PURGE',
                'TRACE'],
                true
            )
        ) {
            return $this->method = $method;
        }

        if (!(bool)preg_match('/^[A-Z]++$/D', $method, $matches)) {
            throw new SuspiciousOperationException(sprintf('Invalid method override "%s".', $method));
        }

        return $this->method = $method;
    }

    /**
     * Gets the "real" request method.
     *
     * @see getMethod()
     */
    public function getRealMethod(): string
    {
        return strtoupper((string)$this->server->get('REQUEST_METHOD', 'GET'));
    }

    /**
     * Gets the mime type associated with the format.
     */
    public function getMimeType(string $format): ?string
    {
        if (isset(self::$formats[$format])) {
            return self::$formats[$format][0];
        }
        return null;
    }

    /**
     * Gets the mime types associated with the format.
     *
     * @return string[]
     */
    public static function getMimeTypes(string $format)
    {
        return self::$formats[$format] ?? [];
    }

    /**
     * Gets the format associated with the mime type.
     */
    public function getFormat(?string $mimeType): ?string
    {
        $canonicalMimeType = null;
        $pos = strpos((string)$mimeType, ';');
        if ($mimeType !== null && $pos !== false) {
            $canonicalMimeType = trim(substr($mimeType, 0, $pos));
        }

        # TODO initializeFormats() add functionality to add your own formats
//        if (static::$formats === null) {
//            static::initializeFormats();
//        }

        foreach (self::$formats as $format => $mimeTypes) {
            if (\in_array($mimeType, $mimeTypes, true)) {
                return (string)$format;
            }
            if ($canonicalMimeType !== null && \in_array($canonicalMimeType, $mimeTypes, true)) {
                return (string)$format;
            }
        }

        return null;
    }

    /**
     * Associates a format with mime types.
     *
     * @param string|string[] $mimeTypes2 The associated mime types (the preferred one must be the first as it will be used as the content type)
     */
    public function setFormat(string $format, $mimeTypes2): void
    {
        if (\is_array($mimeTypes2)) {
            /** @var string[] $mimeTypes */
            $mimeTypes = array_map('strval', $mimeTypes2);
            self::$formats[$format] = $mimeTypes;
        } else {
            self::$formats[$format][] = (string)$mimeTypes2;
        }
    }

    /**
     * Gets the request format.
     *
     * Here is the process to determine the format:
     *
     *  * format defined by the user (with setRequestFormat())
     *  * _format request attribute
     *  * $default
     *
     * @see getPreferredFormat
     */
    public function getRequestFormat(?string $default = 'html'): ?string
    {
        $this->format ??= $this->attributes->get('_format');

        return $this->format ?? $default;
    }

    /**
     * Sets the request format.
     */
    public function setRequestFormat(?string $format): void
    {
        $this->format = $format;
    }

    /**
     * Gets the usual name of the format associated with the request's media type (provided in the Content-Type header).
     *
     * @see Request::FORMATS
     */
    public function getContentTypeFormat(): ?string
    {
        return $this->getFormat($this->headers->get('CONTENT_TYPE', ''));
    }

    /**
     * Checks if the request method is of specified type.
     *
     * @param string $method Uppercase request method (GET, POST etc)
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Checks whether or not the method is safe.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
     */
    public function isMethodSafe(): bool
    {
        return \in_array($this->getMethod(), ['GET', 'HEAD', 'OPTIONS', 'TRACE'], true);
    }

    /**
     * Checks whether or not the method is idempotent.
     */
    public function isMethodIdempotent(): bool
    {
        return \in_array($this->getMethod(), ['HEAD', 'GET', 'PUT', 'DELETE', 'TRACE', 'OPTIONS', 'PURGE'], true);
    }

    /**
     * Checks whether the method is cacheable or not.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.3
     */
    public function isMethodCacheable(): bool
    {
        return \in_array($this->getMethod(), ['GET', 'HEAD'], true);
    }

    /**
     * Returns the protocol version.
     *
     * If the application is behind a proxy, the protocol version used in the
     * requests between the client and the proxy and between the proxy and the
     * server might be different. This returns the former (from the "Via" header)
     * if the proxy is trusted (see "setTrustedProxies()"), otherwise it returns
     * the latter (from the "SERVER_PROTOCOL" server parameter).
     */
    public function getProtocolVersion(): ?string
    {
        if ($this->isFromTrustedProxy()) {
            preg_match('~^(HTTP/)?([1-9]\.[0-9]) ~', $this->headers->get('Via') ?? '', $matches);

            if (count($matches) !== 0) {
                return 'HTTP/' . $matches[2];
            }
        }

        return (string)$this->server->get('SERVER_PROTOCOL');
    }

//    /**
//     * Returns the request body content.
//     *
//     * @param bool $asResource If true, a resource will be returned
//     *
//     * @return false|string|null
//     * @psalm-return ($asResource is true ? resource : string)
//     */
//    public function getContent(bool $asResource = false)

    /**
     * Returns the request body content.
     */
    public function getContent(): ?string
    {
        // There is no resource type in KPHP
        // fixme: this method has reduced functionality

        if ($this->content === null) {
            $fileGetContents = file_get_contents('php://input');
            if ($fileGetContents !== false) {
                $this->content = $fileGetContents;
            }
        }

        return $this->content;
    }

    /**
     * Gets the request body decoded as array, typically from a JSON payload.
     *
     * @return mixed
     *
     * @throws JsonException When the body cannot be decoded to an array
     */
    public function toArray()
    {
        $content = $this->getContent();

        if ($content === '') {
            throw new JsonException('Request body is empty.');
        }

        $content = json_decode((string)$content, true);

        if (!\is_array($content)) {
            throw new JsonException(
                sprintf(
                    'JSON content was expected to decode to an array, "%s" returned.',
                    gettype($content)
                )
            );
        }

        return $content;
    }

    /**
     * Gets the Etags.
     */
    public function getETags(): array
    {
        $ETags = preg_split('/\s*,\s*/', $this->headers->get('If-None-Match', ''), -1, \PREG_SPLIT_NO_EMPTY);
        if ($ETags === false) {
            return [];
        }
        return $ETags;
    }

    public function isNoCache(): bool
    {
        return $this->headers->hasCacheControlDirective('no-cache')
            || $this->headers->get('Pragma') == 'no-cache';
    }

    /**
     * Gets the preferred format for the response by inspecting, in the following order:
     *   * the request format set using setRequestFormat;
     *   * the values of the Accept HTTP header.
     *
     * Note that if you use this method, you should send the "Vary: Accept" header
     * in the response to prevent any issues with intermediary HTTP caches.
     */
    public function getPreferredFormat(?string $default = 'html'): ?string
    {
        if ($this->preferredFormat === null) {
            $this->preferredFormat = $this->getRequestFormat(null);
        }
        if ($this->preferredFormat !== null) {
            return $this->preferredFormat;
        }

        foreach ($this->getAcceptableContentTypes() as $mimeType) {
            $this->preferredFormat = $this->getFormat($mimeType);
            if ((bool)$this->preferredFormat) {
                return $this->preferredFormat;
            }
        }

        return $default;
    }

    /**
     * Returns the preferred language.
     *
     * @param string[] $locales An array of ordered available locales
     */
    public function getPreferredLanguage($locales = []): ?string
    {
        $preferredLanguages = $this->getLanguages();

        if (empty($locales)) {
            return $preferredLanguages[0] ?? null;
        }

        if ($preferredLanguages === []) {
            return $locales[0];
        }


        $extendedPreferredLanguages = [];
        foreach ($preferredLanguages as $language) {
            $extendedPreferredLanguages[] = $language;
            $position = strpos($language, '_');
            if ($position !== false) {
                $superLanguage = (string)substr($language, 0, $position);
                if (!\in_array($superLanguage, $preferredLanguages, true)) {
                    $extendedPreferredLanguages[] = $superLanguage;
                }
            }
        }

        /** @var string[] $extendedPreferredLanguagesString */
        $extendedPreferredLanguagesString = array_map('strval', $extendedPreferredLanguages);

        $preferredLanguages = array_values(array_intersect($extendedPreferredLanguagesString, $locales));

        /** @var string[] $preferredLanguagesString */
        $preferredLanguagesString = array_map('strval', $preferredLanguages);

        return $preferredLanguagesString[0] ?? $locales[0];
    }

    /**
     * Gets a list of languages acceptable by the client browser ordered in the user browser preferences.
     *
     * @return string[]
     */
    public function getLanguages()
    {
        $thisLanguages = $this->languages;
        if ($thisLanguages !== null) {
            return $thisLanguages;
        }

        $languages = AcceptHeader::fromString($this->headers->get('Accept-Language'))->all();
        $this->languages = [];
        foreach ($languages as $acceptHeaderItem) {
            $lang = $acceptHeaderItem->getValue();
            if (strpos($lang, '-') !== false) {
                $codes = explode('-', $lang);
                if ($codes[0] === 'i') {
                    // Language not listed in ISO 639 that are not variants
                    // of any listed language, which can be registered with the
                    // i-prefix, such as i-cherokee
                    if (\count($codes) > 1) {
                        $lang = (string)$codes[1];
                    }
                } else {
                    for ($i = 0, $max = \count($codes); $i < $max; ++$i) {
                        if ($i === 0) {
                            $lang = strtolower($codes[0]);
                        } else {
                            $lang .= '_' . strtoupper($codes[$i]);
                        }
                    }
                }
            }

            $this->languages[] = $lang;
        }

        $thisLanguages = $this->languages;
        if ($thisLanguages === null) {
            return [];
        }

        return $thisLanguages;
    }

    /**
     * Gets a list of charsets acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function getCharsets()
    {
        $charsets = $this->charsets;
        if ($charsets !== null) {
            return $charsets;
        }
        $charsets = array_map(
            'strval',
            array_keys(AcceptHeader::fromString($this->headers->get('Accept-Charset'))->all())
        );
        $this->charsets = $charsets;

        return $charsets;
    }

    /**
     * Gets a list of encodings acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function getEncodings()
    {
        $encodings = $this->encodings;
        if ($encodings !== null) {
            return $encodings;
        }

        $encodings = array_map(
            'strval',
            array_keys(AcceptHeader::fromString($this->headers->get('Accept-Encoding'))->all())
        );
        $this->encodings = $encodings;

        return $encodings;
    }

    /**
     * Gets a list of content types acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function getAcceptableContentTypes()
    {
        $acceptableContentTypes = $this->acceptableContentTypes;
        if ($acceptableContentTypes !== null) {
            return $acceptableContentTypes;
        }

        $acceptableContentTypes = array_map(
            'strval',
            array_keys(
                AcceptHeader::fromString($this->headers->get('Accept'))->all()
            )
        );

        $this->acceptableContentTypes = $acceptableContentTypes;

        return $acceptableContentTypes;
    }

    /**
     * Returns true if the request is an XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     */
    public function isXmlHttpRequest(): bool
    {
        return $this->headers->get('X-Requested-With') == 'XMLHttpRequest';
    }

    /**
     * Checks whether the client browser prefers safe content or not according to RFC8674.
     *
     * @see https://tools.ietf.org/html/rfc8674
     */
    public function preferSafeContent(): bool
    {
        if ($this->isSafeContentPreferred !== null) {
            return (bool)$this->isSafeContentPreferred;
        }

        if ($this->isSecure() === false) {
            $this->isSafeContentPreferred = false;
            // see https://tools.ietf.org/html/rfc8674#section-3
            return false;
        }

        $this->isSafeContentPreferred = AcceptHeader::fromString($this->headers->get('Prefer'))->has('safe');
        return (bool)$this->isSafeContentPreferred;
    }

    /*
     * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
     *
     * Code subject to the new BSD license (https://framework.zend.com/license).
     *
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (https://www.zend.com/)
     */

    private function prepareRequestUri(): string
    {
        $requestUri = '';

        if (
            $this->server->get('IIS_WasUrlRewritten') == '1'
            && $this->server->get('UNENCODED_URL') != ''
        ) {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $requestUri = $this->server->get('UNENCODED_URL', '');
            $this->server->remove('UNENCODED_URL');
            $this->server->remove('IIS_WasUrlRewritten');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = (string)$this->server->get('REQUEST_URI', '');

            if (strlen($requestUri) !== 0 && ($requestUri)[0] === '/') {
                // To only use path and query remove the fragment.
                $pos = strpos($requestUri, '#');
                if ($pos !== false) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path,
                // only use URL path.
                /** @var mixed $uriComponents2 */
                $uriComponents2 = parse_url($requestUri);
                /** @var string[] $uriComponents */
                $uriComponents = array_map('strval', $uriComponents2);

                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }

                if (isset($uriComponents['query'])) {
                    $requestUri .= '?' . $uriComponents['query'];
                }
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $requestUri = (string)$this->server->get('ORIG_PATH_INFO', '');
            if ($this->server->get('QUERY_STRING') != '') {
                $requestUri .= '?' . $this->server->get('QUERY_STRING', '');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }

        // normalize the request URI to ease creating sub-requests from this request
        $this->server->set('REQUEST_URI', (string)$requestUri);

        return (string)$requestUri;
    }

    /**
     * Prepares the base URL.
     */
    private function prepareBaseUrl(): string
    {
        $filename = basename((string)$this->server->get('SCRIPT_FILENAME', ''));

        if (basename((string)$this->server->get('SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME');
        } elseif (basename((string)$this->server->get('PHP_SELF', '')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF');
        } elseif (basename((string)$this->server->get('ORIG_SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('ORIG_SCRIPT_NAME'); // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = (string)$this->server->get('PHP_SELF', '');
            $file = (string)$this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = \count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                $index++;
                $pos = strpos($path, $baseUrl);
            } while ($last > $index && ($pos !== false) && $pos != 0);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = (string)$this->getRequestUri();
        if (strlen($requestUri) !== 0 && $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl);
        if ((bool)$baseUrl && $prefix !== null) {
            // full $baseUrl matches
            return $prefix;
        }

        $prefix = $this->getUrlencodedPrefix(
            $requestUri,
            rtrim(
                \dirname((string)$baseUrl),
                '/' . self::DIRECTORY_SEPARATOR
            ) . '/'
        );
        if ((bool)$baseUrl && $prefix !== null) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/' . self::DIRECTORY_SEPARATOR);
        }

        $truncatedRequestUri = $requestUri;
        $pos = strpos($requestUri, '?');
        if ($pos !== false) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl ?? '');
        if (empty($basename) || !(bool)strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        $baseUrl = (string)$baseUrl;
        $pos = strpos($requestUri, $baseUrl);
        if (\strlen($requestUri) >= \strlen($baseUrl) && ($pos !== false) && $pos !== 0) {
            $baseUrl = substr($requestUri, 0, $pos + \strlen($baseUrl));
        }

        return rtrim($baseUrl, '/' . self::DIRECTORY_SEPARATOR);
    }

    /**
     * Prepares the base path.
     */
    protected function prepareBasePath(): ?string
    {
        $baseUrl = $this->getBaseUrl();
        if (empty($baseUrl)) {
            return '';
        }

        $filename = basename((string)$this->server->get('SCRIPT_FILENAME'));
        if (basename($baseUrl) === $filename) {
            $basePath = \dirname($baseUrl);
        } else {
            $basePath = $baseUrl;
        }

        $count = [];
        if ('\\' === self::DIRECTORY_SEPARATOR) {
            $basePath = str_replace('\\', '/', $basePath, $count);
        }

        return rtrim($basePath, '/');
    }

    /**
     * Prepares the path info.
     */
    private function preparePathInfo(): ?string
    {
        $requestUri = (string)$this->getRequestUri();

        if ($requestUri === '') {
            return '/';
        }

        // Remove the query string from REQUEST_URI
        $pos = strpos($requestUri, '?');
        if ($pos !== false) {
            $requestUri = (string)substr($requestUri, 0, $pos);
        }
        if (strlen($requestUri) !== 0 && $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        $baseUrl = $this->getBaseUrlReal();

        $pathInfo = substr($requestUri, \strlen((string)$baseUrl));
        if (!(bool)$pathInfo || $pathInfo === '') {
            // If substr() returns false then PATH_INFO is set to an empty string
            return '/';
        }

        return $pathInfo;
    }

    /**
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, null otherwise.
     */
    private function getUrlencodedPrefix(?string $string, ?string $prefix): ?string
    {
        if (
            !(isset($string) && isset($prefix))
            || !str_starts_with(rawurldecode($string), $prefix)
        ) {
            return null;
        }

        $len = \strlen($prefix);

        if ((bool)preg_match(sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return (string)$match[0];
        }

        return null;
    }

    /**
     * Indicates whether this request originated from a trusted proxy.
     *
     * This can be useful to determine whether or not to trust the
     * contents of a proxy-specific header.
     */
    public function isFromTrustedProxy(): bool
    {
        if (count(self::$trustedProxies) !== 0) {
            return IpUtils::checkIp((string)$this->server->get('REMOTE_ADDR', ''), self::$trustedProxies);
        }
        return false;
    }

    /** @return string[] */
    private function getTrustedValues(int $type, ?string $ip = null)
    {
        /** @var string[] $clientValues */
        $clientValues = [];
        /** @var string[] $forwardedValues */
        $forwardedValues = [];

        if ((bool)(self::$trustedHeaderSet & $type) && $this->headers->has(self::TRUSTED_HEADERS[$type])) {
            foreach (explode(',', (string)$this->headers->get(self::TRUSTED_HEADERS[$type])) as $v) {
                /** @var string[] $clientValues */
                $clientValues[] = ($type === self::HEADER_X_FORWARDED_PORT ? '0.0.0.0:' : '') . trim($v);
            }
        }

        if (
            (bool)(self::$trustedHeaderSet & self::HEADER_FORWARDED) &&
            (isset(self::FORWARDED_PARAMS[$type])) &&
            $this->headers->has(self::TRUSTED_HEADERS[self::HEADER_FORWARDED])
        ) {
            $forwarded = $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
            $parts = HeaderUtils::split((string)$forwarded, ',;=');
            $param = self::FORWARDED_PARAMS[$type];
            foreach ($parts as $subParts) {
                $v = HeaderUtils::combine($subParts)[$param] ?? null;
                if ($v === null) {
                    continue;
                }
                if ($type === self::HEADER_X_FORWARDED_PORT) {
                    $v = strrchr($v, ':');
                    if (str_ends_with((string)$v, ']') || $v === false) {
                        $v = $this->isSecure() ? ':443' : ':80';
                    }
                    $v = '0.0.0.0' . $v;
                }
                /** @var string[] $forwardedValues */
                $forwardedValues[] = (string)$v;
            }
        }

        if ($ip !== null) {
            $clientValues = $this->normalizeAndFilterClientIps($clientValues, $ip);
            $forwardedValues = $this->normalizeAndFilterClientIps($forwardedValues, $ip);
        }

        if ($forwardedValues === $clientValues || count($clientValues) === 0) {
            return $forwardedValues;
        }

        if (count($forwardedValues) === 0) {
            return $clientValues;
        }

        if (!$this->isForwardedValid) {
            if ($ip !== null) {
                return ['0.0.0.0', $ip];
            }
            /** @var string[] $stringArray */
            $stringArray = [];
            return $stringArray;
        }
        $this->isForwardedValid = false;

        throw new ConflictingHeadersException(
            sprintf(
                'The request has both a trusted "%s" header and a trusted "%s" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure your project to distrust the offending one.',
                self::TRUSTED_HEADERS[self::HEADER_FORWARDED],
                self::TRUSTED_HEADERS[$type]
            )
        );
    }

    /**
     * @param string[] $clientIps
     * @return string[]
     */
    private function normalizeAndFilterClientIps($clientIps, string $ip)
    {
        if (count($clientIps) === 0) {
            /** @var string[] $stringArray */
            $stringArray = [];
            return $stringArray;
        }
        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
        $firstTrustedIp = null;

        foreach ($clientIps as $key => $clientIp) {
            if (!\is_bool(strpos($clientIp, '.'))) {
                // Strip :port from IPv4 addresses. This is allowed in Forwarded
                // and may occur in X-Forwarded-For.
                $i = strpos($clientIp, ':');
                if (!\is_bool($i)) {
                    $clientIp = (string)substr($clientIp, 0, $i);
                    $clientIps[$key] = $clientIp;
                }
            } elseif (str_starts_with($clientIp, '[')) {
                // Strip brackets and :port from IPv6 addresses.
                $i = strpos($clientIp, ']', 1);
                $clientIp = (string)substr($clientIp, 1, $i - 1);
                $clientIps[$key] = $clientIp;
            }

            $regexIpV4 = '([0-9]{1,3}[\.]){3}[0-9]{1,3}';
            $regexIpV6 = '(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))';


            if (
                (bool)preg_match("/^{$regexIpV4}$/", $clientIp, $matches) === false &&
                (bool)preg_match("/^{$regexIpV6}$/", $clientIp, $matches) === false
            ) {
                unset($clientIps[$key]);

                continue;
            }

            if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                unset($clientIps[$key]);

                // Fallback to this when the client IP falls into the range of trusted proxies
                $firstTrustedIp ??= $clientIp;
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        if (count($clientIps) !== 0) {
            $arrayReverse = array_reverse($clientIps);
            /** @var string[] $stringArrayRevers */
            $stringArrayRevers = array_map('strval', $arrayReverse);
            return $stringArrayRevers;
        }
        if ($firstTrustedIp === null) {
            /** @var string[] $stringArray */
            $stringArray = [];
            return $stringArray;
        }
        return [$firstTrustedIp];
    }
}
