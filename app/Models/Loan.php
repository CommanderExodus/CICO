<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Carbon\CarbonInterface;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ausleihe eines Einzelgegenstands mit Zeitraum, Ausleiher und Status (R5).
 *
 * @property LoanStatus $status
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 */
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'borrower_id',
        'starts_on',
        'ends_on',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'reserved',
    ];

    /**
     * Die generierte Spalte period gehört der Datenbank.
     *
     * @var list<string>
     */
    protected $hidden = [
        'period',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoanStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * Ausleiher sehen nur ihre eigenen Ausleihen (R22), Verwalter und Admins alle.
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->canManageInventory()) {
            $query->where('borrower_id', $user->id);
        }
    }

    /**
     * @param  Builder<Loan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', LoanStatus::activeValues());
    }

    /**
     * Überfällig ist abgeleitet: ausgegeben und Rückgabedatum überschritten (R5).
     *
     * @param  Builder<Loan>  $query
     */
    public function scopeOverdue(Builder $query, ?CarbonInterface $today = null): void
    {
        $query->where('status', LoanStatus::Issued)
            ->whereDate('ends_on', '<', ($today ?? today())->toDateString());
    }

    /**
     * @param  Builder<Loan>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereRaw("period && daterange(?::date, ?::date, '[]')", [$from->toDateString(), $to->toDateString()]);
    }

    public function isOverdue(?CarbonInterface $today = null): bool
    {
        return $this->status === LoanStatus::Issued
            && $this->ends_on->lt(($today ?? today())->startOfDay());
    }
}
