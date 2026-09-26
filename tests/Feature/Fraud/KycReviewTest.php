<?php

namespace Tests\Feature\Fraud;

use App\Enums\KycStatus;
use App\Models\Admin;
use App\Models\KycDocument;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class KycReviewTest extends TestCase
{
    use RefreshDatabase;

    private Admin $support;

    private KycDocument $document;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->support = Admin::factory()->create()->assignRole('support');

        // Submitted the same way the member page does it.
        $member = Member::factory()->active()->create();
        $this->actingAs($member->user)->post(route('kyc.store'), [
            'type' => 'nid',
            'document_number' => '1234567890',
            'document' => UploadedFile::fake()->image('nid-front.jpg', 800, 500),
            'photo' => UploadedFile::fake()->image('selfie.jpg', 400, 400),
        ]);
        $this->document = KycDocument::query()->where('member_id', $member->id)->firstOrFail();
    }

    public function test_the_queue_lists_submissions_by_status()
    {
        KycDocument::factory()->create(['status' => KycStatus::Approved]);

        $this->actingAs($this->support, 'admin')
            ->get(route('admin.kyc.index'))
            ->assertOk()
            ->assertViewHas('documents', fn ($page) => $page->total() === 1 && $page->first()->is($this->document));

        $this->actingAs($this->support, 'admin')
            ->get(route('admin.kyc.index', ['status' => 'approved']))
            ->assertViewHas('documents', fn ($page) => $page->total() === 1 && ! $page->first()->is($this->document));
    }

    public function test_the_viewer_streams_the_private_files()
    {
        $files = $this->document->getMedia('documents')->merge($this->document->getMedia('photo'));
        $this->assertCount(2, $files);

        $this->actingAs($this->support, 'admin')
            ->get(route('admin.kyc.show', $this->document))
            ->assertOk()
            ->assertSee(route('admin.kyc.media', [$this->document, $files->first()]), false);

        $response = $this->actingAs($this->support, 'admin')->get(route('admin.kyc.media', [$this->document, $files->first()]));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_a_file_of_another_submission_cannot_be_fetched_through_this_one()
    {
        $other = KycDocument::factory()->create();
        $foreign = $this->document->getFirstMedia('photo');
        $this->assertNotNull($foreign);

        $this->actingAs($this->support, 'admin')
            ->get(route('admin.kyc.media', [$other, $foreign]))
            ->assertNotFound();
    }

    public function test_the_files_are_not_reachable_by_members_or_guests()
    {
        $media = $this->document->getFirstMedia('photo');
        $this->assertNotNull($media);

        $this->get(route('admin.kyc.media', [$this->document, $media]))->assertRedirect(route('admin.login'));

        $finance = Admin::factory()->create()->assignRole('finance');
        $this->actingAs($finance, 'admin')->get(route('admin.kyc.media', [$this->document, $media]))->assertForbidden();
    }

    public function test_approve_records_the_reviewer_and_audits()
    {
        $this->actingAs($this->support, 'admin')
            ->post(route('admin.kyc.approve', $this->document))
            ->assertRedirect(route('admin.kyc.index'))
            ->assertSessionHas('success');

        $this->document->refresh();
        $this->assertSame(KycStatus::Approved, $this->document->status);
        $this->assertSame($this->support->id, $this->document->reviewed_by);
        $this->assertNotNull($this->document->reviewed_at);

        $log = Activity::query()->where('log_name', 'kyc')->latest('id')->firstOrFail();
        $this->assertSame('KYC approved', $log->description);
        $this->assertTrue($log->causer?->is($this->support));
    }

    public function test_reject_requires_a_reason_that_the_member_sees()
    {
        $this->actingAs($this->support, 'admin')
            ->post(route('admin.kyc.reject', $this->document), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(KycStatus::Pending, $this->document->fresh()?->status);

        $this->actingAs($this->support, 'admin')
            ->post(route('admin.kyc.reject', $this->document), ['reason' => 'Photo is blurred'])
            ->assertRedirect(route('admin.kyc.index'));

        $this->document->refresh();
        $this->assertSame(KycStatus::Rejected, $this->document->status);
        $this->assertSame('Photo is blurred', $this->document->rejection_reason);
        $this->assertTrue(Activity::query()->where('description', 'KYC rejected')->exists());
    }

    public function test_a_decided_submission_cannot_be_decided_again()
    {
        $this->actingAs($this->support, 'admin')->post(route('admin.kyc.approve', $this->document));

        $this->actingAs($this->support, 'admin')
            ->from(route('admin.kyc.show', $this->document))
            ->post(route('admin.kyc.reject', $this->document), ['reason' => 'Changed my mind'])
            ->assertRedirect(route('admin.kyc.show', $this->document))
            ->assertSessionHas('error');

        $this->assertSame(KycStatus::Approved, $this->document->fresh()?->status);
    }
}
