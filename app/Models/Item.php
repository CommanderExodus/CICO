<?php

namespace App\Models;

use App\Enums\ItemAvailability;
use App\Enums\ItemKind;
use App\Enums\LoanStatus;
use App\Support\PublicId;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ein inventarisierter Gegenstand: Einzelgegenstand mit Seriennummer oder Mengenware (R1).
 *
 * @property ItemKind $kind
 */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'name',
        'description',
        'category_id',
        'location_id',
        'serial_number',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ItemKind::class,
            'quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            $item->public_id ??= PublicId::generate();
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Die aktuell ausgegebene Ausleihe, falls vorhanden (höchstens eine, siehe loans_one_issued_per_item).
     *
     * @return HasOne<Loan, $this>
     */
    public function currentLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->where('status', LoanStatus::Issued);
    }

    /**
     * @return HasMany<FoundReport, $this>
     */
    public function foundReports(): HasMany
    {
        return $this->hasMany(FoundReport::class);
    }

    public function isUnit(): bool
    {
        return $this->kind === ItemKind::Unit;
    }

    public function availability(): ItemAvailability
    {
        if ($this->kind === ItemKind::Bulk) {
            return $this->quantity > 0 ? ItemAvailability::Available : ItemAvailability::OutOfStock;
        }

        return $this->currentLoan !== null ? ItemAvailability::OnLoan : ItemAvailability::Available;
    }

    public function publicUrl(): string
    {
        return route('public.items.show', $this->public_id);
    }
}
