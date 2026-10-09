<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugGenerator
{
    /**
     * The format a slug must follow when an admin types one by hand.
     */
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Generated slugs are cut to this length so a numeric suffix always fits the slug columns.
     */
    private const int MAX_BASE_LENGTH = 100;

    /**
     * Build a slug from the given text that no other row of the model's table uses.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function generate(string $modelClass, string $text, ?Model $ignore = null): string
    {
        $base = rtrim(Str::limit(Str::slug($text), self::MAX_BASE_LENGTH, ''), '-');

        if ($base === '') {
            $base = Str::slug(class_basename($modelClass));
        }

        $slug = $base;
        $suffix = 2;

        while ($this->isTaken($modelClass, $slug, $ignore)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function isTaken(string $modelClass, string $slug, ?Model $ignore): bool
    {
        return $modelClass::query()
            ->where('slug', $slug)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}
