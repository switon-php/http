<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit\Server;

use Switon\Http\Server\Network;
use Switon\Http\Tests\TestCase;

class NetworkTest extends TestCase
{
    public function testContainsWithExactMatch(): void
    {
        $result = Network::contains('192.168.1.1', '192.168.1.1');

        $this->assertTrue($result);
    }

    public function testContainsWithWildcard(): void
    {
        $result = Network::contains('*', '192.168.1.1');

        $this->assertTrue($result);
    }

    public function testContainsWithCidrNotation(): void
    {
        $result = Network::contains('192.168.1.0/24', '192.168.1.100');

        $this->assertTrue($result);
    }

    public function testContainsWithCidrNotationDoesNotMatch(): void
    {
        $result = Network::contains('192.168.1.0/24', '192.168.2.100');

        $this->assertFalse($result);
    }

    public function testContainsWithCidrOnlyMode(): void
    {
        $result = Network::contains('192.168.1.0/24', '192.168.1.100', true);

        $this->assertTrue($result);
    }

    public function testContainsWithCidrOnlyModeRejectsWildcard(): void
    {
        $result = Network::contains('192.168.1.*', '192.168.1.100', true);

        $this->assertFalse($result);
    }

    public function testContainsWithRange(): void
    {
        $result = Network::contains('192.168.1.1-192.168.1.10', '192.168.1.5');

        $this->assertTrue($result);
    }

    public function testContainsWithRangeDoesNotMatch(): void
    {
        $result = Network::contains('192.168.1.1-192.168.1.10', '192.168.1.20');

        $this->assertFalse($result);
    }

    public function testContainsWithWildcardPrefix(): void
    {
        $result = Network::contains('192.168.1.*', '192.168.1.100');

        $this->assertTrue($result);
    }

    public function testContainsWithArray(): void
    {
        $result = Network::contains(['192.168.1.1', '192.168.2.1'], '192.168.1.1');

        $this->assertTrue($result);
    }

    public function testContainsWithCommaSeparatedString(): void
    {
        $result = Network::contains('192.168.1.1, 192.168.2.1', '192.168.1.1');

        $this->assertTrue($result);
    }

    public function testContainsWithEmptyHaystackReturnsFalse(): void
    {
        $this->assertFalse(Network::contains('', '127.0.0.1'));
    }

    public function testContainsWhitespaceOnlyHaystackStringReturnsFalse(): void
    {
        $this->assertFalse(Network::contains("  \t  ,  \n  ", '127.0.0.1'));
    }

    public function testContainsEmptyArrayHaystackReturnsFalse(): void
    {
        $this->assertFalse(Network::contains([], '127.0.0.1'));
    }

    public function testContainsWildcardPrefixDoesNotMatchDifferentThirdOctet(): void
    {
        $this->assertFalse(Network::contains('10.0.1.*', '10.0.2.5'));
    }

    public function testContainsSingleIpStringRequiresExactMatch(): void
    {
        $this->assertFalse(Network::contains('192.168.1.1', '192.168.1.2'));
    }

    public function testContainsSplitsTabSeparatedHaystackString(): void
    {
        $this->assertTrue(Network::contains("192.168.1.1\t192.168.2.1", '192.168.2.1'));
    }

    public function testContainsWildcardEntryInArrayMatchesAnyIpv4(): void
    {
        $this->assertTrue(Network::contains(['*'], '8.8.8.8'));
    }

    public function testContainsWildcardPrefixRejectsDifferentLeadingOctets(): void
    {
        $this->assertFalse(Network::contains('192.168.*', '10.168.1.1'));
    }

    public function testContainsSlash32MatchesSingleHost(): void
    {
        $this->assertTrue(Network::contains('198.51.100.10/32', '198.51.100.10'));
        $this->assertFalse(Network::contains('198.51.100.10/32', '198.51.100.11'));
    }

    public function testContainsRangeIncludesStartAndEndAddresses(): void
    {
        $range = '192.168.1.1-192.168.1.10';
        $this->assertTrue(Network::contains($range, '192.168.1.1'));
        $this->assertTrue(Network::contains($range, '192.168.1.10'));
    }

    public function testContainsCidrOnlyRejectsNeedleOutsidePrefix(): void
    {
        $this->assertFalse(Network::contains(['192.168.0.0/16'], '10.0.0.1', true));
    }

    public function testContainsWithDottedNetmask(): void
    {
        $this->assertTrue(Network::contains('10.1.0.0/255.255.0.0', '10.1.99.7'));
        $this->assertFalse(Network::contains('10.1.0.0/255.255.0.0', '10.2.99.7'));
    }

    public function testContainsCidrOnlyWithExactIpEntry(): void
    {
        $this->assertTrue(Network::contains(['203.0.113.5'], '203.0.113.5', true));
        $this->assertTrue(Network::contains(['203.0.113.0/24'], '203.0.113.5', true));
        $this->assertFalse(Network::contains(['203.0.113.0/24'], '203.0.114.5', true));
        $this->assertFalse(Network::contains(['192.168.1.*'], '192.168.1.5', true));
    }

    public function testLocalReturnsString(): void
    {
        $result = Network::local();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testLocalReturnsValidIpFormat(): void
    {
        $result = Network::local();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+\.\d+$/', $result);
    }
}
