<?php

namespace Database\Factories\Documents;

use App\Models\Company;
use App\Models\Documents\DocumentFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentFolder>
 */
class DocumentFolderFactory extends Factory
{
    protected $model = DocumentFolder::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => ucfirst(fake()->unique()->word()),
            'is_private' => false,
        ];
    }

    public function privateTo(User $owner): static
    {
        return $this->state(['is_private' => true, 'owner_id' => $owner->id]);
    }
}
