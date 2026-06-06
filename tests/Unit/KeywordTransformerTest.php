<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Http\KeywordTransformer;
use Switon\Http\Tests\TestCase;

/**
 * @group http
 */
class KeywordTransformerTest extends TestCase
{
    public function testTransformKeepsOriginalFieldsWhenKeywordIsNotEmailLike(): void
    {
        $transformer = new KeywordTransformer();

        $result = $transformer->transform(['admin_name', 'email'], 'mark', '*=');

        $this->assertSame('admin_name,email*=', $result);
    }

    public function testTransformPrefersEmailLikeFieldsWhenKeywordContainsAt(): void
    {
        $transformer = new KeywordTransformer();

        $result = $transformer->transform(['admin_name', 'email', 'backup_email'], 'mark@example.com', '*=');

        $this->assertSame('email,backup_email*=', $result);
    }

    public function testTransformFallsBackWhenNoEmailLikeFieldExists(): void
    {
        $transformer = new KeywordTransformer();

        $result = $transformer->transform(['admin_name', 'nickname'], 'mark@example.com', '*=');

        $this->assertSame('admin_name,nickname*=', $result);
    }

    public function testTransformDetectsEmailLikeFieldsCaseInsensitively(): void
    {
        $transformer = new KeywordTransformer();

        $result = $transformer->transform(['name', 'UserEMail', 'contact_email_addr'], 'x@y.com', '*=');

        $this->assertSame('UserEMail,contact_email_addr*=', $result);
    }

    public function testTransformWithEmptyFieldListProducesOperatorOnlySuffix(): void
    {
        $transformer = new KeywordTransformer();

        $this->assertSame('*=', $transformer->transform([], 'any', '*='));
        $this->assertSame('~=', $transformer->transform([], 'a@b.com', '~='));
    }
}
