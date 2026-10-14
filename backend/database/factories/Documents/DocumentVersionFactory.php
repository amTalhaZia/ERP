<?php

namespace Database\Factories\Documents;

use App\Models\Documents\Document;
use App\Models\Documents\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        $contents = fake()->sentence();

        return [
            'document_id' => Document::factory(),
            'company_id' => fn (array $attributes) => Document::withoutTenancy()->findOrFail($attributes['document_id'])->company_id,
            'version' => 1,
            'file_path' => 'documents/'.Str::uuid()->toString().'.txt',
            'original_name' => 'file.txt',
            'mime' => 'text/plain',
            'size_bytes' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
        ];
    }
}
