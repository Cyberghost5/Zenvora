<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'bank_name', 'bank_code', 'account_number', 'account_name', 'is_primary'])]
class BankAccount extends Model
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "••••••4321" -- safe to render in lists and confirmations. */
    public function maskedNumber(): string
    {
        $last = Str::substr($this->account_number, -4);

        return str_repeat('•', max(0, Str::length($this->account_number) - 4)).$last;
    }
}
