<?php

namespace App\Support\Payments;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The stretch of time an admin is looking at on the payments page: a quick
 * choice such as "this month", or a custom range between two dates.
 */
class PaymentPeriod
{
    public const ALL = 'all';

    public const TODAY = 'today';

    public const WEEK = 'week';

    public const MONTH = 'month';

    public const YEAR = 'year';

    public const CUSTOM = 'custom';

    /**
     * @var list<string>
     */
    public const OPTIONS = [self::ALL, self::TODAY, self::WEEK, self::MONTH, self::YEAR, self::CUSTOM];

    private function __construct(
        public readonly string $key,
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {}

    /**
     * Read the period from the request. Anything unrecognised means "all time".
     */
    public static function fromRequest(Request $request): self
    {
        $now = CarbonImmutable::now();
        $key = (string) $request->query('period', self::ALL);

        return match ($key) {
            self::TODAY => new self($key, $now->startOfDay(), $now->endOfDay()),
            self::WEEK => new self($key, $now->startOfWeek(), $now->endOfWeek()),
            self::MONTH => new self($key, $now->startOfMonth(), $now->endOfMonth()),
            self::YEAR => new self($key, $now->startOfYear(), $now->endOfYear()),
            self::CUSTOM => self::custom($request),
            default => new self(self::ALL, null, null),
        };
    }

    /**
     * A custom range with either end left open when its date is missing or invalid.
     */
    private static function custom(Request $request): self
    {
        $from = self::parseDate($request->query('from'));
        $to = self::parseDate($request->query('to'));

        if ($from !== null && $to !== null && $to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return new self(self::CUSTOM, $from?->startOfDay(), $to?->endOfDay());
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * Narrow a query on a timestamp column to this period.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply($query, string $column = 'created_at')
    {
        return $query
            ->when($this->start, fn ($query) => $query->where($column, '>=', $this->start))
            ->when($this->end, fn ($query) => $query->where($column, '<=', $this->end));
    }

    /**
     * What the page sends back so the form shows the active choice.
     *
     * @return array{period: string, from: string, to: string}
     */
    public function toFilters(): array
    {
        return [
            'period' => $this->key,
            'from' => $this->key === self::CUSTOM ? ($this->start?->format('Y-m-d') ?? '') : '',
            'to' => $this->key === self::CUSTOM ? ($this->end?->format('Y-m-d') ?? '') : '',
        ];
    }

    /**
     * A short phrase for file names, such as "this-month" or "2026-01-01_to_2026-01-31".
     */
    public function slug(): string
    {
        if ($this->key !== self::CUSTOM) {
            return match ($this->key) {
                self::ALL => 'all-time',
                self::TODAY => 'today',
                default => 'this-'.$this->key,
            };
        }

        return ($this->start?->format('Y-m-d') ?? 'start').'_to_'.($this->end?->format('Y-m-d') ?? 'today');
    }
}
