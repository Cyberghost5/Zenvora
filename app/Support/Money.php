<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * An amount of money, held as an integer count of minor units (kobo).
 *
 * Every balance, plan bound and transaction amount in this application is a
 * Money. Floats are rejected at the boundary rather than tolerated, because
 * rounding drift on a ledger compounds silently and cannot be reconciled
 * after the fact.
 */
final class Money implements \JsonSerializable
{
    public const MINOR_UNITS = 100;

    private function __construct(public readonly int $minor) {}

    /**
     * Build from a raw minor-unit integer, which is what the database stores.
     */
    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    /**
     * Build from a major-unit value as typed by a human ("15000", "15,000.50").
     *
     * Parsing is done on the decimal string rather than by multiplying a float,
     * so 0.1 cannot arrive as 0.09999999999999999 and truncate to 9 kobo.
     */
    public static function fromMajor(int|float|string $major): self
    {
        $raw = trim((string) $major);

        if ($raw === '') {
            return new self(0);
        }

        // Tolerate thousands separators and a leading currency symbol.
        $raw = str_replace([',', ' ', '₦'], '', $raw);

        if (! preg_match('/^-?\d*(\.\d*)?$/', $raw) || $raw === '.' || $raw === '-') {
            throw new InvalidArgumentException("Not a valid money amount: {$major}");
        }

        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-');

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');

        // Pad or truncate the fraction to exactly two digits. Anything finer
        // than a kobo is discarded rather than rounded up, so the platform can
        // never credit a fraction it did not receive.
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        $minor = (int) ($whole === '' ? 0 : $whole) * self::MINOR_UNITS + (int) $fraction;

        return new self($negative ? -$minor : $minor);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    /**
     * Apply a basis-point rate. 100bp == 1%.
     *
     * intdiv floors the result, so any sub-kobo remainder stays with the
     * platform instead of being rounded in the user's favour on every accrual.
     */
    public function percentageBp(int $basisPoints): self
    {
        return new self(intdiv($this->minor * $basisPoints, 10_000));
    }

    public function multiply(int $factor): self
    {
        return new self($this->minor * $factor);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->minor < $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        return $this->minor > $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    /**
     * Major units as a float. For display and JSON only -- never feed the
     * result back into a calculation.
     */
    public function toMajor(): float
    {
        return $this->minor / self::MINOR_UNITS;
    }

    /**
     * "12,500.00" -- grouped, always two decimal places, no symbol.
     */
    public function format(): string
    {
        return number_format($this->minor / self::MINOR_UNITS, 2);
    }

    /**
     * "₦12,500.00"
     */
    public function formatWithSymbol(): string
    {
        return config('zenvora.currency_symbol', '₦').$this->format();
    }

    /**
     * "₦12.5k" / "₦1.2m" -- for stat tiles where horizontal space is tight.
     */
    public function formatCompact(): string
    {
        $symbol = config('zenvora.currency_symbol', '₦');
        $major = abs($this->toMajor());
        $sign = $this->isNegative() ? '-' : '';

        return match (true) {
            $major >= 1_000_000_000 => $sign.$symbol.rtrim(rtrim(number_format($major / 1_000_000_000, 2, '.', ''), '0'), '.').'b',
            $major >= 1_000_000 => $sign.$symbol.rtrim(rtrim(number_format($major / 1_000_000, 2, '.', ''), '0'), '.').'m',
            $major >= 10_000 => $sign.$symbol.rtrim(rtrim(number_format($major / 1_000, 1, '.', ''), '0'), '.').'k',
            default => $sign.$symbol.$this->format(),
        };
    }

    public function __toString(): string
    {
        return $this->formatWithSymbol();
    }

    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'formatted' => $this->formatWithSymbol(),
        ];
    }
}
