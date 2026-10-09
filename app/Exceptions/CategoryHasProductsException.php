<?php

namespace App\Exceptions;

use App\Models\Category;
use Exception;

class CategoryHasProductsException extends Exception
{
    public function __construct(public readonly Category $category)
    {
        parent::__construct("Category [{$category->id}] still has products and cannot be deleted.");
    }
}
