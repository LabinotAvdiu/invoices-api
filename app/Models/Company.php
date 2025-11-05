<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Delete logo when company is deleted
        static::deleting(function (Company $company) {
            $company->load('logo');
            if ($company->logo) {
                $oldPath = $company->logo->path;
                if ($oldPath && str_contains($oldPath, '/storage/')) {
                    $filePath = str_replace('/storage/', '', parse_url($oldPath, PHP_URL_PATH));
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
                }
                $company->logo->delete();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'legal_form',
        'siret',
        'address',
        'zip_code',
        'city',
        'country',
        'phone',
        'email',
        'creation_date',
        'sector',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'creation_date' => 'date',
    ];

    /**
     * The users that belong to the company.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get the company's logo (attachment).
     * Only one logo is allowed per company.
     */
    public function logo()
    {
        return $this->morphOne(Attachment::class, 'model');
    }

    /**
     * Get all the company's attachments.
     */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'model');
    }

    /**
     * Set or update the company logo.
     * This will delete the old logo if one exists.
     *
     * @param array $logoData
     * @return Attachment
     */
    public function setLogo(array $logoData): Attachment
    {
        // Delete the old logo if it exists (including the physical file)
        if ($this->logo) {
            $oldPath = $this->logo->path;
            // Extract the file path from the URL
            if ($oldPath && str_contains($oldPath, '/storage/')) {
                $filePath = str_replace('/storage/', '', parse_url($oldPath, PHP_URL_PATH));
                \Illuminate\Support\Facades\Storage::disk('public')->delete($filePath);
            }
            $this->logo->delete();
        }

        // Create the new logo
        return $this->logo()->create($logoData);
    }
}


