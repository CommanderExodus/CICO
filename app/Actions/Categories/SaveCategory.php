<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCategory
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input, ?Category $category = null): Category
    {
        $category === null
            ? Gate::forUser($actor)->authorize('create', Category::class)
            : Gate::forUser($actor)->authorize('update', $category);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category?->id)],
        ])->validate();

        $category ??= new Category;
        $category->name = trim($validated['name']);

        try {
            DB::transaction(fn () => $category->save());
        } catch (QueryException $e) {
            if (ConstraintViolation::is($e, 'categories_name_unique')) {
                throw ValidationException::withMessages(['name' => 'Diese Kategorie gibt es bereits.']);
            }

            throw $e;
        }

        return $category;
    }
}
