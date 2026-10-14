<?php

namespace Database\Factories\Documents;

use App\Enums\Documents\DocumentableType;
use App\Enums\Documents\DocumentCategory;
use App\Enums\Documents\DocumentVisibility;
use App\Models\Company;
use App\Models\Documents\Document;
use App\Models\Documents\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'category' => DocumentCategory::Other,
            'tags' => ['general'],
            'visibility' => DocumentVisibility::Company,
            'is_archived' => false,
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(['owner_id' => $user->id]);
    }

    public function private(): static
    {
        return $this->state(['visibility' => DocumentVisibility::Private]);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function restrictedTo(array $roleIds): static
    {
        return $this->state(['visibility' => DocumentVisibility::Restricted, 'allowed_role_ids' => $roleIds]);
    }

    public function forEmployee(int $employeeId): static
    {
        return $this->state([
            'category' => DocumentCategory::Employee,
            'documentable_type' => DocumentableType::Employee->value,
            'documentable_id' => $employeeId,
        ]);
    }

    /** Adds a version 1 row (and sets it current); pair with Storage::fake('local') + a stored file when downloading. */
    public function withVersion(string $path = 'documents/test.txt', string $contents = 'hello'): static
    {
        return $this->afterCreating(function (Document $document) use ($path, $contents): void {
            $version = DocumentVersion::query()->create([
                'company_id' => $document->company_id,
                'document_id' => $document->id,
                'version' => 1,
                'file_path' => $path,
                'original_name' => basename($path),
                'mime' => 'text/plain',
                'size_bytes' => strlen($contents),
                'checksum_sha256' => hash('sha256', $contents),
            ]);

            $document->forceFill(['current_version_id' => $version->id])->saveQuietly();
        });
    }
}
