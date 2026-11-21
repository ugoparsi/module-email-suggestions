<?php

/**
 * SPDX-FileCopyrightText: 2026 Ugo Parsi
 * SPDX-License-Identifier: AFL-3.0
 */

declare(strict_types=1);

use Amp\Cancellation;
use Amp\Dns\DnsException;
use Amp\Dns\DnsRecord;
use Amp\Dns\DnsResolver;
use Amp\Dns\MissingDnsRecordException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Amp\delay;

require_once __DIR__ . '/../app/code/community/UgoParsi/EmailSuggestions/Service/Domain.php';

final class DomainTest extends TestCase
{
    public function testRejectsAnAddressAndOtherNonDomainInputWithoutCallingDns(): void
    {
        $resolver = $this->createMock(DnsResolver::class);
        $resolver->expects($this->never())->method('query');
        $service = new UgoParsi_EmailSuggestions_Service_Domain($resolver);

        foreach ([
            null, [], '', 'shopper@sample.example.com', 'https://sample.example.com', 'sample.example.com:53',
            'localhost', '127.0.0.1', '[::1]', 'sample.example.com.', '-invalid.example.com',
            'service.local', 'service.internal', 'router.home.arpa', 'sample.test', 'sample.invalid',
            str_repeat('a', 1025) . '.com',
        ] as $input) {
            self::assertSame('unknown', $service->check($input));
        }
    }

    public function testNormalizesAnInternationalizedDomainBeforeLookup(): void
    {
        $resolver = $this->createMock(DnsResolver::class);
        $resolver->expects($this->once())->method('query')
            ->with('xn--bcher-kva.example.com', DnsRecord::MX)
            ->willReturn([new DnsRecord('10,mx.example.com', DnsRecord::MX)]);
        $service = new UgoParsi_EmailSuggestions_Service_Domain($resolver);

        self::assertSame('has_mail', $service->check('BÜCHER.Example.COM'));
    }

    #[DataProvider('dnsAnswers')]
    public function testChecksMailRecordsWithOneTimeout(array $queries, string $expected): void
    {
        $resolver = $this->createMock(DnsResolver::class);
        $timeout = null;
        $resolver->expects($this->exactly(count($queries)))->method('query')->willReturnCallback(
            static function (string $domain, int $type, Cancellation $cancellation) use (&$queries, &$timeout): array {
                self::assertSame('sample.example.com', $domain);
                $timeout ??= $cancellation;
                self::assertSame($timeout, $cancellation);
                [$expectedType, $result] = array_shift($queries);
                self::assertSame($expectedType, $type);
                if ($result instanceof Throwable) {
                    throw $result;
                }
                return [new DnsRecord($result, $type)];
            },
        );

        self::assertSame($expected, new UgoParsi_EmailSuggestions_Service_Domain($resolver)->check('SaMpLe.ExAmPlE.CoM'));
    }

    public static function dnsAnswers(): iterable
    {
        $missing = new MissingDnsRecordException();
        yield 'mail exchange' => [[[DnsRecord::MX, '10,mx.example.com']], 'has_mail'];
        yield 'null mail exchange' => [[[DnsRecord::MX, '0,']], 'no_mail'];
        yield 'IPv4 fallback' => [[[DnsRecord::MX, $missing], [DnsRecord::A, '192.0.2.12']], 'has_mail'];
        yield 'IPv6 fallback' => [[[DnsRecord::MX, $missing], [DnsRecord::A, $missing], [DnsRecord::AAAA, '2001:db8::12']], 'has_mail'];
        yield 'no mail records' => [[[DnsRecord::MX, $missing], [DnsRecord::A, $missing], [DnsRecord::AAAA, $missing]], 'no_mail'];
        yield 'domain missing' => [[[DnsRecord::MX, new DnsException('NXDOMAIN', 3)]], 'no_mail'];
        yield 'resolver failure' => [[[DnsRecord::MX, new DnsException('SERVFAIL', 2)]], 'unknown'];
        yield 'fallback failure' => [[[DnsRecord::MX, $missing], [DnsRecord::A, new DnsException('REFUSED', 5)]], 'unknown'];
    }

    public function testSlowResolverReturnsUnknown(): void
    {
        $resolver = $this->createMock(DnsResolver::class);
        $resolver->expects($this->once())->method('query')->willReturnCallback(
            static function (string $domain, int $type, Cancellation $cancellation): array {
                delay(1.5, cancellation: $cancellation);
                return [new DnsRecord('10,mx.example.com', $type)];
            },
        );

        self::assertSame('unknown', new UgoParsi_EmailSuggestions_Service_Domain($resolver)->check('sample.example.com'));
    }
}
