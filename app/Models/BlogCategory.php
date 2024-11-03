<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory;
    protected $table = 'blog_categories';
    protected $fillable = [
        'name', 'name_al', 'description', 'description_al', 'venue_id', 'bybest_id'
    ];

    public function blogs()
    {
        return $this->belongsToMany(Blog::class);
    }
}
