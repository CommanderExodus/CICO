<?php

namespace App\Models;

use App\Enums\AuditChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Eintrag im Audit-Trail (R18). Datenänderungen schreiben die Trigger selbst;
 * die Anwendung ergänzt nur Ereignisse ohne Datenänderung, z. B. MCP-Aufrufe (R33).
 * Die Tabelle ist per Trigger unveränderlich.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'user_id',
        'channel',
        'client',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
            'channel' => AuditChannel::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit-Einträge sind unveränderlich.'));
        static::deleting(fn () => throw new LogicException('Audit-Einträge sind unveränderlich.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
