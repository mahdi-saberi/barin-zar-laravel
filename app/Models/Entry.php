<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = ['kind', 'slug', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
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
