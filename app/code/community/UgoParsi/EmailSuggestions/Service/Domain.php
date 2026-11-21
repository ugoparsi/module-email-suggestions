<?php

/**
 * SPDX-FileCopyrightText: 2026 Ugo Parsi
 * SPDX-License-Identifier: AFL-3.0
 */

declare(strict_types=1);

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver;
use Amp\Dns\MissingDnsRecordException;
use Amp\Dns\Rfc1035StubDnsResolver;
use Amp\Dns\StaticDnsConfigLoader;
use Amp\Dns\UnixDnsConfigLoader;
use Amp\Dns\WindowsDnsConfigLoader;
use Amp\Process\ProcessException;
use Amp\TimeoutCancellation;
use Maho\Security\RateLimitScope;

use function Amp\async;

class UgoParsi_EmailSuggestions_Service_Domain
{
    private const string XML_PATH_ENABLED = 'customer/email_suggestions/check_domain';
    private const string XML_PATH_RATE_LIMIT = 'system/rate_limit/email_suggestions_domain';
    private const float LOOKUP_TIMEOUT = 0.35;
    private const int DNS_NAME_ERROR = 3;

    public const string HAS_MAIL = 'has_mail';
    public const string NO_MAIL = 'no_mail';
    public const string UNKNOWN = 'unknown';

    public function __construct(private readonly ?DnsResolver $resolver = null) {}

    public function checkForStorefront(mixed $domain): string
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_ENABLED)
            || !Mage::helper('core')->rateLimiter(
                'email_suggestions_domain',
                (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT, Mage_Core_Model_App::ADMIN_STORE_ID),
                60,
                RateLimitScope::Ip,
            )->attempt()
        ) {
            return self::UNKNOWN;
        }

        return $this->check($domain);
    }

    public function check(mixed $domain): string
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === null) {
            return self::UNKNOWN;
        }

        $cancellation = new TimeoutCancellation(self::LOOKUP_TIMEOUT);
        try {
            $resolver = $this->resolver ?? async($this->createResolver(...))->await($cancellation);
            $mx = $this->query($resolver, $domain, DnsRecord::MX, $cancellation);
            if ($mx !== []) {
                return count($mx) === 1 && in_array(trim($mx[0]->getValue()), ['0,', '0,.', '0 .'], true)
                    ? self::NO_MAIL
                    : self::HAS_MAIL;
            }
            foreach ([DnsRecord::A, DnsRecord::AAAA] as $type) {
                if ($this->query($resolver, $domain, $type, $cancellation) !== []) {
                    return self::HAS_MAIL;
                }
            }
        } catch (CancelledException|ProcessException) {
            return self::UNKNOWN;
        } catch (DnsException $error) {
            return $error->getCode() === self::DNS_NAME_ERROR ? self::NO_MAIL : self::UNKNOWN;
        }

        return self::NO_MAIL;
    }

    private function normalizeDomain(mixed $domain): ?string
    {
        if (!is_string($domain) || $domain === '' || strlen($domain) > 1024) {
            return null;
        }

        $ascii = idn_to_ascii(
            $domain,
            IDNA_USE_STD3_RULES | IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ,
            INTL_IDNA_VARIANT_UTS46,
        );
        if ($ascii === false || !preg_match('/\.[a-z][a-z0-9-]*$/D', $ascii)
            || filter_var($ascii, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || preg_match('/\.(?:alt|arpa|corp|example|home|internal|invalid|lan|local|localhost|onion|test)$/D', $ascii)
        ) {
            return null;
        }

        return $ascii;
    }

    private function createResolver(): DnsResolver
    {
        $loader = PHP_OS_FAMILY === 'Windows' ? new WindowsDnsConfigLoader() : new UnixDnsConfigLoader();
        $config = $loader->loadConfig()->withTimeout(self::LOOKUP_TIMEOUT)->withAttempts(1);

        return new Rfc1035StubDnsResolver(configLoader: new StaticDnsConfigLoader($config));
    }

    /** @return list<DnsRecord> */
    private function query(DnsResolver $resolver, string $domain, int $type, Cancellation $cancellation): array
    {
        try {
            return $resolver->query($domain, $type, $cancellation);
        } catch (MissingDnsRecordException) {
            return [];
        }
    }
}
