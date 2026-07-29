<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'admin_id', 'admin_name', 'action', 'subject_type', 'subject_id',
    'description', 'before', 'after', 'ip_address',
])]
class AdminAuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function subjectLabel(): ?string
    {
        if (! $this->subject_type) {
            return null;
        }

        return class_basename($this->subject_type).' #'.$this->subject_id;
    }
}
