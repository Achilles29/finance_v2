<?php
require_once __DIR__.'/CustomerLocalConfig.php';

/**
 * Resolves deployment configuration from the process environment.
 *
 * This class intentionally has no logging or output side effects. A snapshot
 * may be supplied by tests so they do not need to modify the process
 * environment.
 */
final class DeploymentConfig
{
    const ENCRYPTION_KEY = 'FINANCE_ENCRYPTION_KEY';
    const DB_HOST = 'FINANCE_DB_HOST';
    const DB_PORT = 'FINANCE_DB_PORT';
    const DB_SOCKET = 'FINANCE_DB_SOCKET';
    const DB_NAME = 'FINANCE_DB_NAME';
    const DB_USER = 'FINANCE_DB_USER';
    const DB_PASSWORD = 'FINANCE_DB_PASSWORD';
    const BASE_URL = 'FINANCE_BASE_URL';
    const SESSION_PATH = 'FINANCE_SESSION_PATH';
    const SESSION_COOKIE = 'FINANCE_SESSION_COOKIE';
    const LOG_PATH = 'FINANCE_LOG_PATH';
    const CACHE_PATH = 'FINANCE_CACHE_PATH';

    /**
     * @var array
     */
    private $environment;
    private $local = false;
    private $databaseOnly = false;

    /**
     * @param array|null $snapshot Explicit values are intended for tests.
     */
    public function __construct($snapshot = null, $root = null)
    {
        if ($snapshot === null) {
            $root = $root ?? dirname(__DIR__, 2);
            $snapshot = self::snapshotEnvironment(null, $root);
            $this->local = CustomerLocalConfig::present($root);
            $this->databaseOnly = $this->local && CustomerLocalConfig::databaseOnly(CustomerLocalConfig::read($root));
        }

        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Deployment configuration snapshot must be an array.');
        }

        $this->environment = $snapshot;
    }

    /**
     * Build a resolver from a caller-owned environment snapshot.
     *
     * @param array $snapshot
     * @return self
     */
    public static function fromSnapshot($snapshot)
    {
        return new self($snapshot);
    }

    /**
     * Take a read-only snapshot of the requested process environment values.
     *
     * @param array|null $names
     * @return array
     */
    public static function snapshotEnvironment($names = null, $root = null)
    {
        if ($names === null) {
            $names = self::allowedNames();
        }

        $file = self::deploymentFileSnapshot();
        $env = array();
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $env[$name] = $value;
            }
        }

        $root = $root ?? dirname(__DIR__, 2);
        if (CustomerLocalConfig::present($root)) {
            $local = CustomerLocalConfig::read($root);
            CustomerLocalConfig::merge($local, $file, $env); // Conflicting DB sources always fail closed.
            return CustomerLocalConfig::databaseOnly($local) ? array_replace($file, $env, $local) : $local;
        }
        return array_replace($file, $env);
    }

    public static function allowedNames()
    {
        return array_merge(self::productionRequiredNames(), array(self::DB_PORT, self::DB_SOCKET,
            self::BASE_URL, self::SESSION_PATH, self::SESSION_COOKIE, self::LOG_PATH, self::CACHE_PATH));
    }

    /** Installer resolver for an extracted target, not the tool's own development installation. */
    public static function forRoot($root)
    {
        $local = CustomerLocalConfig::read($root);
        if (CustomerLocalConfig::databaseOnly($local)) throw new RuntimeException('CUSTOMER_INSTALL_REQUIRES_COMPLETE_CONFIG');
        return self::fromLocalValues($local);
    }

    /** Authorized installer preview; never persists or silently overrides conflicting legacy sources. */
    public static function previewCustomer($root,array $configuration)
    {
        return self::fromLocalValues(CustomerLocalConfig::validate($root,json_encode($configuration,JSON_THROW_ON_ERROR)));
    }

    private static function fromLocalValues(array $local)
    {
        $env = array();
        foreach (self::allowedNames() as $name) if (getenv($name) !== false) $env[$name] = getenv($name);
        $resolver = new self(CustomerLocalConfig::merge($local, self::deploymentFileSnapshot(), $env));
        $resolver->local = true;
        return $resolver;
    }

    public function isLocal() { return $this->local; }
    public function values() { return $this->environment; }

    public function hasExplicitDatabase()
    {
        if ($this->local || getenv('FINANCE_DEPLOYMENT_FILE') !== false) return true;
        foreach (array(self::DB_HOST, self::DB_PORT, self::DB_SOCKET, self::DB_NAME, self::DB_USER, self::DB_PASSWORD) as $key) {
            if (array_key_exists($key, $this->environment)) return true;
        }
        return false;
    }

    /** Public signed context stays OUTSIDE source; no private agent key is moved here. */
    public function customerEnvironment($root, array $environment)
    {
        if ($this->local && !$this->databaseOnly) {
            $context = CustomerLocalConfig::runtime($this->environment).'/customer-installation.json';
            if (!empty($environment['FINANCE_CUSTOMER_INSTALLATION_FILE'])
                && $environment['FINANCE_CUSTOMER_INSTALLATION_FILE'] !== $context) {
                throw new RuntimeException('CUSTOMER_CONFIG_CONTEXT_CONFLICT');
            }
            $environment['FINANCE_CUSTOMER_INSTALLATION_FILE'] = $context;
        } elseif (CustomerLocalConfig::packaged($root) && empty($environment['FINANCE_CUSTOMER_INSTALLATION_FILE'])) {
            throw new RuntimeException('CUSTOMER_INSTALLATION_CONTEXT_REQUIRED');
        }
        return $environment;
    }

    /** Optional root-owned JSON secret file, outside the webroot; never executable PHP. */
    private static function deploymentFileSnapshot()
    {
        $path = getenv('FINANCE_DEPLOYMENT_FILE');
        if ($path === false || $path === '') return array();
        $real = realpath($path);
        $root = realpath(dirname(__DIR__, 2));
        if (PHP_OS_FAMILY === 'Windows' || $real === false || $real !== $path || $root === false
            || is_link($path) || !is_file($path) || !is_readable($path) || filesize($path) > 16384
            || strpos($real . '/', $root . '/') === 0) {
            throw new RuntimeException('Deployment file is unavailable.');
        }
        $stat = stat($path);
        if (!is_array($stat) || $stat['uid'] !== 0 || ($stat['mode'] & 0027) !== 0) throw new RuntimeException('Deployment file is unavailable.');
        for ($parent = dirname($real); ; $parent = dirname($parent)) {
            $stat = stat($parent);
            if (!is_array($stat) || $stat['uid'] !== 0 || ($stat['mode'] & 0022) !== 0) throw new RuntimeException('Deployment file is unavailable.');
            if ($parent === dirname($parent)) break;
        }
        $values = json_decode((string)file_get_contents($path), true);
        if (!is_array($values)) throw new RuntimeException('Deployment file is unavailable.');
        $allowed = self::allowedNames();
        foreach ($values as $name => $value) {
            if (!in_array($name, $allowed, true) || !is_string($value)) throw new RuntimeException('Deployment file is unavailable.');
        }
        return $values;
    }

    /** Explicit runtime directories must be pre-provisioned outside application source. */
    public function runtimeDirectory($name, $webroot, $fallback)
    {
        $path = $this->get($name, '');
        if ($path === '') return $fallback; // Preserve existing installations until explicitly configured.
        $portableRoot=CustomerPlatform::root($webroot);
        if(CustomerPlatform::portable($portableRoot)) {
            $allowed=[self::LOG_PATH=>'logs',self::CACHE_PATH=>'cache',self::SESSION_PATH=>'sessions'];
            if(!isset($allowed[$name]) || $path!==$portableRoot.'/storage/'.$allowed[$name])throw new RuntimeException('Runtime directory is unavailable.');
            // Only these three mutable leaves, not arbitrary code/config or the private agent store.
            CustomerPlatform::path($portableRoot,$portableRoot.'/storage');
            if(str_replace('\\','/',(string)realpath($path))!==$path || is_link($path)||!is_dir($path)||!is_writable($path)
                ||(PHP_OS_FAMILY==='Linux' && ((fileperms($path)&0007)!==0 || fileowner($path)!==posix_geteuid())))throw new RuntimeException('Runtime directory is unavailable.');
            if(PHP_OS_FAMILY==='Windows')CustomerPlatform::runtimeAcl($portableRoot,$path);
            return $path.DIRECTORY_SEPARATOR;
        }
        $real = is_string($path) ? realpath($path) : false;
        $root = realpath($webroot);
        if ($real === false || $root === false || $real !== rtrim($path, '/\\') || is_link($path)
            || !is_dir($real) || !is_writable($real) || strpos($real . '/', $root . '/') === 0
            || (fileperms($real) & 0007) !== 0) throw new RuntimeException('Runtime directory is unavailable.');
        return $real . DIRECTORY_SEPARATOR;
    }

    public function canonicalBaseUrl($fallback, $production)
    {
        $url = $this->get(self::BASE_URL, '');
        if ($url === '') return $fallback;
        $parts = is_string($url) ? parse_url($url) : false;
        if (!is_array($parts) || !filter_var($url, FILTER_VALIDATE_URL) || empty($parts['host'])
            || !in_array($parts['scheme'] ?? '', $production ? array('https') : array('https', 'http'), true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || preg_match('/[\x00-\x20\x7f]/', $url)) throw new RuntimeException('Canonical URL is unavailable.');
        return rtrim($url, '/') . '/';
    }

    public function sessionCookieName()
    {
        $name = $this->get(self::SESSION_COOKIE, 'finance_session');
        if (!is_string($name) || preg_match('/\A[A-Za-z][A-Za-z0-9_]{5,63}\z/D', $name) !== 1) throw new RuntimeException('Session configuration is unavailable.');
        return $name;
    }

    /**
     * Return the names covered by the production contract.
     *
     * @return array
     */
    public static function productionRequiredNames()
    {
        return array(
            self::ENCRYPTION_KEY,
            self::DB_HOST,
            self::DB_NAME,
            self::DB_USER,
            self::DB_PASSWORD,
        );
    }

    /**
     * Resolve an optional value without exposing or logging it.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->environment)
            ? $this->environment[$name]
            : $default;
    }

    /**
     * Resolve a required value.
     *
     * The exception deliberately contains no variable name or value because
     * callers may route exception text to an external error handler.
     *
     * @param string $name
     * @return mixed
     */
    public function required($name)
    {
        $value = $this->get($name);
        if (!$this->hasContent($value)) {
            throw new RuntimeException('Required deployment configuration is unavailable.');
        }

        return $value;
    }

    /**
     * Return missing or blank names without returning any associated values.
     *
     * @param array|null $names
     * @return array
     */
    public function missingRequired($names = null)
    {
        if ($names === null) {
            $names = self::productionRequiredNames();
        }

        $missing = array();
        foreach ($names as $name) {
            if (!$this->hasContent($this->get($name))) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Validate the production secret/configuration contract.
     *
     * @return bool
     */
    public function validateProductionSecretContract()
    {
        return count($this->missingRequired()) === 0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function hasContent($value)
    {
        return is_scalar($value) && trim((string)$value) !== '';
    }
}
