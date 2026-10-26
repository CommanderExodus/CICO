<?php

namespace App\Actions\Categories;

use App\Models\Category;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteCategory
{
    public function handle(User $actor, Category $category): void
    {
        Gate::forUser($actor)->authorize('delete', $category);

        try {
            DB::transaction(fn () => $category->delete());
        } catch (QueryException $e) {
            if (ConstraintViolation::isForeignKey($e)) {
                throw ValidationException::withMessages([
                    'category' => "„{$category->name}“ ist noch Gegenständen zugeordnet und kann nicht gelöscht werden.",
                ]);
            }

            throw $e;
        }
    }
}
