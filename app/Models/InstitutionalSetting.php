<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class InstitutionalSetting extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'organization_name',
        'unit_name',
        'logo_path',
        'address',
        'manager_name',
        'management_year',
        'document_footer',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
