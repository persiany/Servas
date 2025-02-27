<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class Group extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = [
        'title',
        'user_id'
    ];

    public string $searchableType = 'Groups';

    /**
     * Get all of the links that are assigned this group.
     */
    public function links(): MorphToMany
    {
        return $this->morphedByMany(Link::class, 'groupable');
    }

    /**
     * Get all child groups.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_group_id');
    }

    public function getSearchResult(): SearchResult
    {
        $url = route('groups.show', $this->id);

        return new SearchResult(
            $this,
            $this->title,
            $url
        );
    }
}
