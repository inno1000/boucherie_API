<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Animal extends Model
{
    use HasAttachments, HasFactory, HasUuids;

    protected $table = 'animaux';

    protected $fillable = [
        'boucherie_id', 'fournisseur_id', 'achat_fournisseur_id',
        'espece', 'numero_tag', 'poids_vif_kg', 'prix_achat',
        'date_acquisition', 'statut',
    ];

    public function isOwnedByFournisseur(): bool
    {
        return $this->fournisseur_id !== null && $this->boucherie_id === null;
    }

    protected $casts = [
        'poids_vif_kg'     => 'decimal:2',
        'prix_achat'       => 'decimal:2',
        'date_acquisition' => 'date',
    ];

    public function boucherie(): BelongsTo
    {
        return $this->belongsTo(Boucherie::class, 'boucherie_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function achatFournisseur(): BelongsTo
    {
        return $this->belongsTo(AchatFournisseur::class, 'achat_fournisseur_id');
    }

    public function abattage(): HasOne
    {
        return $this->hasOne(Abattage::class, 'animal_id');
    }
}
