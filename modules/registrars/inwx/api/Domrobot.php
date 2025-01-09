<?php

namespace INWX;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class Domrobot implements LoggerAwareInterface
{
    private const VERSION = '0.0.0-semantic-release';
    private const LIVE_URL = 'https://api.domrobot.com/';
    private const OTE_URL = 'https://api.ote.domrobot.com/';
    private const XMLRPC = 'xmlrpc';
    private const JSONRPC = 'jsonrpc';

    private $whmcsVersion;
    private $debug = false;
    private $language = 'en';
    private $customer = '';
    private $clTrid;
    private $cookieFile;

    private $url = self::OTE_URL;
    private $api = self::JSONRPC;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Domrobot constructor.
     *
     * @param string|null $cookieFile You can overwrite the standard cookieFile path by setting a full path here
     */
    public function __construct(?string $cookieFile = null)
    {
        $whmcsDetails = localAPI('WhmcsDetails', [], null);
        $this->whmcsVersion = $whmcsDetails['whmcs']['version'] ?? '8.X.Y';
        $this->logger = new Logger('domrobot_default_logger');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $this->logger->pushProcessor(function ($record) {
            $record->extra['WhmcsDetails'] = $whmcsDetails['whmcs'] ?? [];

            return $record;
        });
        $this->cookieFile = $cookieFile ?? tempnam(sys_get_temp_dir(), 'INWX');
    }

    /**
     * Configures the Domrobot to use the live endpoint. All actions will be executed live and can cause costs if you buy something.
     * It is recommended to try your code with our OTE system before to check if everything works as expected.
     */
    public function useLive(): self
    {
        $this->url = self::LIVE_URL;

        return $this;
    }

    /**
     * Configures the Domrobot to use the OTE endpoint. All actions will be executed in our test environment which has extra credentials.
     * Here you can test for free as much as you like.
     */
    public function useOte(): self
    {
        $this->url = self::OTE_URL;

        return $this;
    }

    /**
     * @return bool Is the Domrobot configured to use the live endpoint?
     */
    public function isLive(): bool
    {
        return $this->url === self::LIVE_URL;
    }

    /**
     * @return bool Is the Domrobot configured to use the OTE endpoint?
     */
    public function isOte(): bool
    {
        return $this->url === self::OTE_URL;
    }

    /**
     * Configures the Domrobot to use the JSON-RPC API. This needs the ext-json PHP extension installed to work.
     * This should be installed by default in PHP.
     */
    public function useJson(): self
    {
        $this->api = self::JSONRPC;

        return $this;
    }

    /**
     * Configures the Domrobot to use the XML-RPC API. This needs the ext-xmlrpc PHP extension installed to work.
     * This may not be installed by default in PHP.
     */
    public function useXml(): self
    {
        $this->api = self::XMLRPC;

        return $this;
    }

    /**
     * @return bool Is the Domrobot configured to use XML-RPC?
     */
    public function isXml(): bool
    {
        return $this->api === self::XMLRPC;
    }

    /**
     * @return string Either 'en', 'de' or 'es'
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * @param string $language Either 'en', 'de' or 'es'
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return bool Is debug mode activated?
     */
    public function getDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug Activate/deactivate debug mode
     */
    public function setDebug(bool $debug = false): self
    {
        $this->debug = $debug;

        return $this;
    }

    public function getCookieFile(): string
    {
        return $this->cookieFile;
    }

    /**
     * @throws \RuntimeException If the cookieFile is not writable or does not exist
     */
    public function setCookieFile(string $file): self
    {
        if ((file_exists($file) && !is_writable($file)) || (!file_exists($file) && !is_writable(dirname($file)))) {
            throw new \RuntimeException("Cannot write cookiefile: '" . $file . "'. Please check file/folder permissions.", 2400);
        }
        $this->cookieFile = $file;

        return $this;
    }

    public function getCustomer(): string
    {
        return $this->customer;
    }

    public function setCustomer(string $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getClTrId(): string
    {
        return $this->clTrid;
    }

    public function setClTrId(string $clTrId): self
    {
        $this->clTrid = $clTrId;

        return $this;
    }

    /**
     * Execute a login command with the API. This is needed before you can do anything else.
     */
    public function login(string $username, string $password, ?string $sharedSecret = null): array
    {
        $params['lang'] = $this->language;
        $params['user'] = $username;
        $params['pass'] = $password;

        $loginRes = $this->call('account', 'login', $params);
        if (!empty($sharedSecret) && $loginRes['code'] == 1000 && !empty($loginRes['resData']['tfa'])) {
            $tan = $this->getSecretCode($sharedSecret);
            $unlockRes = $this->call('account', 'unlock', ['tan' => $tan]);
            if ($unlockRes['code'] != 1000) {
                return $unlockRes;
            }
        }

        return $loginRes;
    }

    /**
     * Execute a API Request and decode the Response to an array for easy usage.
     */
    public function call(string $object, string $method, array $params = []): array
    {
        if ($this->customer !== '') {
            $params['subuser'] = $this->customer;
        }
        if (!empty($this->clTrid)) {
            $params['clTRID'] = $this->clTrid;
        }

        $methodParam = strtolower($object . '.' . $method);

        if ($this->isJson()) {
            $request = json_encode(['method' => $methodParam, 'params' => $params]);
        } else {
            $request = xmlrpc_encode_request(
                $methodParam,
                $params,
                ['encoding' => 'UTF-8', 'escaping' => 'markup', 'verbosity' => 'no_white_space']
            );
        }

        $header[] = 'Content-Type: ' . ($this->isJson() ? 'application/json' : 'text/xml');
        $header[] = 'Connection: keep-alive';
        $header[] = 'Keep-Alive: 300';
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $header[] = 'X-FORWARDED-FOR: ' . $forwardedFor;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . $this->api . '/');
        curl_setopt($ch, CURLOPT_TIMEOUT, 65);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DomRobot/' . self::VERSION . ' (PHP ' . PHP_VERSION . '; WHMCS ' . $this->whmcsVersion);

        $response = curl_exec($ch);
        curl_close($ch);
        if ($this->debug) {
            $this->logger->debug("Request:\n" . $request . "\n");
            $this->logger->debug("Response:\n" . $response . "\n");
        }

        $processedResponse = $this->isJson() ? json_decode($response, true) : xmlrpc_decode($response, 'UTF-8');

        logModuleCall('inwx', $methodParam, $params, $response, $processedResponse, [
            $params['user'],
            $params['pass'],
        ]);

        return $processedResponse;
    }

    /**
     * @return bool Is the Domrobot configured to use JSON-RPC?
     */
    public function isJson(): bool
    {
        return $this->api === self::JSONRPC;
    }

    /**
     * Returns a secret code needed for 2 factor auth.
     */
    private function getSecretCode(string $secret): string
    {
        $timeSlice = floor(time() / 30);
        $codeLength = 6;

        $base32 = new Base32();

        $secretKey = $base32->decode($secret);
        // Pack time into binary string
        $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
        // Hash it with users secret key
        $hmac = hash_hmac('SHA1', $time, $secretKey, true);
        // Use last nipple of result as index/offset
        $offset = ord(substr($hmac, -1)) & 0x0F;
        // grab 4 bytes of the result
        $hashPart = substr($hmac, $offset, 4);

        // Unpak binary value
        $value = unpack('N', $hashPart);
        $value = $value[1];
        // Only 32 bits
        $value &= 0x7FFFFFFF;

        $modulo = 10 ** $codeLength;

        return str_pad($value % $modulo, $codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Execute a logout command with the API.
     */
    public function logout(): array
    {
        $ret = $this->call('account', 'logout');
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }

        return $ret;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
