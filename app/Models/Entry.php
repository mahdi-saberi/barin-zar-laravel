<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = ['kind', 'slug', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('kind', 'product');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(fn (Builder $items) => $items
            ->whereNull('data->published')
            ->orWhere('data->published', true));
    }

    public function scopeMatching(Builder $query, string $term, array $columns): Builder
    {
        $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $matches) use ($columns, $pattern, $grammar) {
            foreach ($columns as $column) {
                $matches->orWhereRaw($grammar->wrap($column)." like ? escape '!'", [$pattern]);
            }
        });
    }

    public static function getData(string $kind, string $slug): array
    {
        return static::where(compact('kind', 'slug'))->first()?->data ?? [];
    }

    public static function putData(string $kind, string $slug, array $data): void
    {
        static::updateOrCreate(compact('kind', 'slug'), ['data' => $data]);
    }
}
