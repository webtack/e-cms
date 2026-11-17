<?php

namespace Webtack\Ecms\Tests\Unit\Content;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\FieldTypes\FieldType;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Tests\TestCase;

class FieldTypesTest extends TestCase
{
    /** @param array<string, mixed> $settings */
    private function passes(string $type, mixed $value, array $settings = []): bool
    {
        $field = new ContentField(['settings' => $settings ?: null]);
        $rules = app(FieldTypeRegistry::class)->get($type)->rules($field);

        return Validator::make(['v' => $value], ['v' => $rules])->passes();
    }

    private function type(string $key): FieldType
    {
        return app(FieldTypeRegistry::class)->get($key);
    }

    public function test_string(): void
    {
        $this->assertTrue($this->passes('string', 'hello'));
        $this->assertFalse($this->passes('string', str_repeat('a', 513)));
        $this->assertFalse($this->passes('string', str_repeat('a', 11), ['max' => 10]));
        $this->assertFalse($this->passes('string', 'ab', ['min' => 3]));
        // The configured max can never exceed the column size.
        $this->assertFalse($this->passes('string', str_repeat('a', 513), ['max' => 9999]));
        $this->assertSame(['eq', 'in', 'contains'], $this->type('string')->operators());
        $this->assertTrue($this->type('string')->searchable());
        $this->assertSame('content_values_string', $this->type('string')->table());
    }

    public function test_text(): void
    {
        $this->assertTrue($this->passes('text', str_repeat('a', 5000)));
        $this->assertFalse($this->passes('text', str_repeat('a', 11), ['max' => 10]));
        $this->assertSame(['eq', 'in', 'contains'], $this->type('text')->operators());
        $this->assertTrue($this->type('text')->searchable());
    }

    public function test_integer(): void
    {
        $this->assertTrue($this->passes('integer', 42));
        $this->assertTrue($this->passes('integer', '42'));
        $this->assertFalse($this->passes('integer', 'abc'));
        $this->assertFalse($this->passes('integer', 1.5));
        $this->assertFalse($this->passes('integer', 5, ['min' => 10]));
        $this->assertFalse($this->passes('integer', 50, ['max' => 10]));
        $this->assertSame(42, $this->type('integer')->prepare('42'));
        $this->assertSame(42, $this->type('integer')->cast('42'));
        $this->assertSame(['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'], $this->type('integer')->operators());
        $this->assertFalse($this->type('integer')->searchable());
    }

    public function test_decimal(): void
    {
        $this->assertTrue($this->passes('decimal', '12.50'));
        $this->assertTrue($this->passes('decimal', 12.5));
        $this->assertTrue($this->passes('decimal', '0.123456'));
        $this->assertTrue($this->passes('decimal', '1.1234560'));
        $this->assertFalse($this->passes('decimal', '1.1234567'));
        $this->assertFalse($this->passes('decimal', 'abc'));
        $this->assertFalse($this->passes('decimal', '5', ['min' => 10]));

        $decimal = $this->type('decimal');
        $this->assertSame('12.5', $decimal->prepare('12.50'));
        $this->assertSame('12.5', $decimal->cast('12.500000'));
        $this->assertSame('12', $decimal->cast('12.000000'));
        $this->assertSame('0.123456', $decimal->cast(0.123456));
        $this->assertSame('-3.1', $decimal->cast('-3.100000'));
        $this->assertSame('0', $decimal->cast('0.000000'));
        $this->assertSame(['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'], $decimal->operators());
    }

    public function test_boolean(): void
    {
        foreach ([true, false, 1, 0, '1', '0'] as $value) {
            $this->assertTrue($this->passes('boolean', $value));
        }
        $this->assertFalse($this->passes('boolean', 'yes'));

        $boolean = $this->type('boolean');
        $this->assertSame(1, $boolean->prepare(true));
        $this->assertSame(0, $boolean->prepare('0'));
        $this->assertTrue($boolean->cast(1));
        $this->assertFalse($boolean->cast('0'));
        $this->assertSame(['eq'], $boolean->operators());
    }

    public function test_date(): void
    {
        $this->assertTrue($this->passes('date', '2026-10-29'));
        $this->assertFalse($this->passes('date', 'not a date'));
        $this->assertFalse($this->passes('date', '2026-01-01', ['min' => '2026-06-01']));
        $this->assertFalse($this->passes('date', '2026-12-01', ['max' => '2026-06-01']));

        $date = $this->type('date');
        $this->assertSame('2026-10-29', $date->prepare('2026-10-29'));
        $this->assertSame('2026-10-29', $date->prepare(CarbonImmutable::parse('2026-10-29 18:00:00')));
        $this->assertInstanceOf(CarbonImmutable::class, $date->cast('2026-10-29'));
        $this->assertSame('2026-10-29', $date->cast('2026-10-29')->toDateString());
        $this->assertSame(['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'], $date->operators());
    }

    public function test_datetime(): void
    {
        $this->assertTrue($this->passes('datetime', '2026-10-29 14:30:00'));
        $this->assertFalse($this->passes('datetime', 'nope'));

        $datetime = $this->type('datetime');
        $this->assertSame('2026-10-29 14:30:00', $datetime->prepare('2026-10-29T14:30:00'));
        $this->assertInstanceOf(CarbonImmutable::class, $datetime->cast('2026-10-29 14:30:00'));
        $this->assertSame('2026-10-29 14:30:00', $datetime->cast('2026-10-29 14:30:00')->format('Y-m-d H:i:s'));
        $this->assertSame(['eq', 'in', 'gt', 'gte', 'lt', 'lte', 'between'], $datetime->operators());
    }
}
