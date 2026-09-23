<?php

namespace Database\Factories;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KycDocument>
 */
class KycDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'type' => KycDocumentType::Nid,
            'document_number' => fake()->numerify('##########'),
            'file_path' => null,
            'status' => KycStatus::Pending,
        ];
    }
}
