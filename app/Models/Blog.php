<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'title_al',
        'content',
        'content_al',
        'restaurant_id',
        'image',
        'views',
        'read_count',
        'is_active',
        'sections',
        'section_1_new_ul_list',
        'slug',
        'slug_related',
        'author_avatar',
        'author_name',
        'author_designation',
        'read_time',
        'has_tags',
        'detail_image',
        'detail_image_2',
        'detail_image_3',
        'detail_image_4',
        'show_quiz',
        'is_new_type',
        'body',
        'tags',
        'category_text',
        'bybest_id',
        // New language fields
        'title_es',
        'title_fr',
        'title_it',
        'title_de',
        'title_pt',
        'body_es',
        'body_fr',
        'body_it',
        'body_de',
        'body_pt',
        'is_featured',
        'is_draft'
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function categories()
    {
        return $this->belongsToMany(BlogCategory::class);
    }

    public function quizzes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Quiz::class);
    }
}
