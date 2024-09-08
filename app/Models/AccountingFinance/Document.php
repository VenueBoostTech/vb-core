<?php

namespace App\Models\AccountingFinance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'file_path',
        'type',
        'upload_date'
    ];

    protected $casts = [
        'upload_date' => 'date',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByUploadDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('upload_date', [$startDate, $endDate]);
    }

    // Methods
    public function getFileUrl()
    {
        return Storage::url($this->file_path);
    }

    public function getFileSize()
    {
        return Storage::size($this->file_path);
    }

    public function getFileExtension()
    {
        return pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    public function delete()
    {
        // Delete the file from storage when deleting the model
        Storage::delete($this->file_path);
        return parent::delete();
    }

    public static function getDocumentCountByType()
    {
        return self::groupBy('type')
            ->selectRaw('type, count(*) as count')
            ->pluck('count', 'type');
    }

    public static function getRecentDocuments($limit = 5)
    {
        return self::orderBy('upload_date', 'desc')
            ->limit($limit)
            ->get();
    }

    public function isImage()
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        return in_array($this->getFileExtension(), $imageExtensions);
    }

    public function isPdf()
    {
        return $this->getFileExtension() === 'pdf';
    }
}
