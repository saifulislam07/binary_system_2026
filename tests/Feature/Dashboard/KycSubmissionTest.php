<?php

namespace Tests\Feature\Dashboard;

use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KycSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->member = Member::factory()->active()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'nid',
            'document_number' => '1234 567 890',
            'document' => UploadedFile::fake()->image('nid-front.jpg', 800, 500),
            'photo' => UploadedFile::fake()->image('selfie.jpg', 400, 400),
            ...$overrides,
        ];
    }

    public function test_page_shows_not_submitted_and_the_profile()
    {
        $this->actingAs($this->member->user)
            ->get(route('kyc.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('kyc/Index')
                ->where('status', 'not_submitted')
                ->where('canSubmit', true)
                ->where('profile.email', $this->member->user->email));
    }

    public function test_member_submits_nid_with_document_and_photo()
    {
        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload())
            ->assertRedirect(route('kyc.index'))
            ->assertSessionHasNoErrors();

        $document = KycDocument::query()->where('member_id', $this->member->id)->firstOrFail();
        $this->assertSame(KycStatus::Pending, $document->status);
        $this->assertSame(KycDocumentType::Nid, $document->type);
        $this->assertSame('1234567890', $document->document_number);
        $this->assertCount(1, $document->getMedia('documents'));
        $this->assertCount(1, $document->getMedia('photo'));
        Storage::disk('local')->assertExists($document->getFirstMedia('documents')?->getPathRelativeToRoot() ?? 'missing');

        $this->actingAs($this->member->user)
            ->get(route('kyc.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('status', 'pending')
                ->where('canSubmit', false)
                ->where('documents.0.number', '••••••7890'));
    }

    public function test_passport_numbers_are_accepted_and_pdf_documents_allowed()
    {
        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload([
                'type' => 'passport',
                'document_number' => 'a0 1234567',
                'document' => UploadedFile::fake()->create('passport.pdf', 300, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('A01234567', KycDocument::query()->firstOrFail()->document_number);
    }

    public function test_cannot_resubmit_while_under_review_but_can_after_rejection()
    {
        $this->actingAs($this->member->user)->post(route('kyc.store'), $this->payload());

        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload())
            ->assertSessionHasErrors('type');
        $this->assertSame(1, KycDocument::query()->count());

        KycDocument::query()->firstOrFail()->forceFill(['status' => KycStatus::Rejected, 'rejection_reason' => 'Blurry photo'])->save();

        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(2, KycDocument::query()->count());
    }

    public function test_invalid_submissions_are_rejected()
    {
        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload([
                'document_number' => '12345',
                'document' => UploadedFile::fake()->create('virus.exe', 10),
                'photo' => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['document_number', 'document', 'photo']);

        $this->actingAs($this->member->user)
            ->post(route('kyc.store'), $this->payload([
                'document' => UploadedFile::fake()->image('huge.jpg')->size(6000),
            ]))
            ->assertSessionHasErrors('document');

        $this->assertSame(0, KycDocument::query()->count());
    }

    public function test_address_can_be_updated()
    {
        $this->actingAs($this->member->user)
            ->patch(route('kyc.address'), ['address' => 'House 7, Road 3, Chattogram'])
            ->assertRedirect(route('kyc.index'));

        $this->assertSame('House 7, Road 3, Chattogram', $this->member->fresh()?->address);
    }
}
