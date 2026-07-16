<?php

namespace Tests\Feature;

use App\Enums\CaseStatus as CaseStatusEnum;
use App\Enums\InvestigationStatus as InvestigationStatusEnum;
use App\Enums\RecommendationStatus as RecommendationStatusEnum;
use App\Enums\ReportStatus;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\CaseAssignment;
use App\Models\CaseRecord;
use App\Models\CaseStatus;
use App\Models\Investigation;
use App\Models\InvestigationStatus;
use App\Models\Permission;
use App\Models\Recommendation;
use App\Models\RecommendationStatus as RecommendationStatusModel;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->seed(MasterDataSeeder::class);
    }

    public function test_recommendation_foundation_tables_and_transition_metadata_exist(): void
    {
        $this->assertTrue(Schema::hasTable('recommendations'));
        $this->assertTrue(Schema::hasTable('recommendation_status_histories'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'case_id'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'investigation_id'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'returned_by'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'revision_note'));
        $this->assertTrue(Schema::hasColumn('recommendations', 'approved_by'));
        $this->assertTrue(Schema::hasColumn('recommendation_statuses', 'valid_transitions'));
        $this->assertTrue(Schema::hasTable('decisions'));
        $this->assertDatabaseCount('decisions', 0);
        $this->assertTrue(Schema::hasTable('evidences'));
        $this->assertDatabaseCount('evidences', 0);

        $drafting = RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail();

        $this->assertContains(RecommendationStatusEnum::SubmittedToLeader->value, $drafting->valid_transitions);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.recommend']);
        $this->assertDatabaseHas('permissions', ['code' => 'cases.review_recommendation']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-16']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-17']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-23']);
        $this->assertDatabaseHas('notification_types', ['code' => 'NOTIF-24']);
    }

    public function test_assigned_satgas_can_create_recommendation_from_completed_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", [
            'investigation_id' => $investigation->id,
            'conclusion' => 'Kesimpulan rekomendasi yang hanya boleh dibaca Satgas assigned.',
            'recommended_actions' => 'Tindakan yang direkomendasikan Satgas.',
            'sanction_recommendation' => 'Rekomendasi sanksi internal.',
            'recovery_recommendation' => 'Rekomendasi pemulihan korban.',
            'prevention_recommendation' => 'Rekomendasi pencegahan kampus.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RecommendationStatusEnum::Drafting->value)
            ->assertJsonPath('data.investigation_id', $investigation->id)
            ->assertJsonPath('data.conclusion', 'Kesimpulan rekomendasi yang hanya boleh dibaca Satgas assigned.')
            ->assertJsonMissingPath('data.investigation.conclusion');

        $this->assertDatabaseHas('recommendations', [
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => 'RECS-01',
        ]);
        $this->assertDatabaseCount('recommendation_status_histories', 1);
    }

    public function test_recommendation_creation_requires_case_status_and_completed_investigation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeInvestigationCase($admin, $satgas);
        $draftInvestigation = $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Planning);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", $this->payload($draftInvestigation))
            ->assertUnprocessable();

        $recommendationCase = $this->makeRecommendationCase($admin, $satgas);
        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/cases/{$recommendationCase->id}/recommendations", $this->payload($draftInvestigation))
            ->assertUnprocessable();

        $this->assertDatabaseCount('recommendations', 0);
    }

    public function test_admin_and_unassigned_satgas_cannot_create_or_read_sensitive_recommendation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($admin);
        $this->postJson("/api/v1/cases/{$case->id}/recommendations", $this->payload($investigation))
            ->assertForbidden();

        $this->actingAsApi($otherSatgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertForbidden();

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::Drafting->value)
            ->assertJsonMissingPath('data.conclusion')
            ->assertJsonMissingPath('data.recommended_actions')
            ->assertJsonMissingPath('data.investigation.conclusion');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.conclusion', 'Kesimpulan rekomendasi rahasia.')
            ->assertJsonMissingPath('data.investigation.conclusion');
    }

    public function test_assigned_satgas_can_update_draft_recommendation_only(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tindakan rekomendasi yang sudah diperbarui.',
        ])
            ->assertOk()
            ->assertJsonPath('data.recommended_actions', 'Tindakan rekomendasi yang sudah diperbarui.');

        $recommendation->forceFill([
            'status_code' => RecommendationStatusModel::query()->where('name', RecommendationStatusEnum::SubmittedToLeader->value)->firstOrFail()->code,
            'submitted_at' => now(),
        ])->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tidak boleh berubah setelah submit.',
        ])
            ->assertUnprocessable();
    }

    public function test_assigned_satgas_can_submit_and_resubmit_a_returned_recommendation(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::SubmittedToLeader->value);

        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'return_for_revision',
            'revision_note' => 'Perjelas dasar rekomendasi dan tindakan tindak lanjut.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::Revised->value)
            ->assertJsonPath('data.leadership_review.revision_note', 'Perjelas dasar rekomendasi dan tindakan tindak lanjut.');

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tindakan rekomendasi yang telah diperbaiki.',
        ])->assertOk();
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::SubmittedToLeader->value);

        $this->assertNotNull($recommendation->refresh()->submitted_at);
        $this->assertSame(CaseStatusEnum::Recommendation->value, $case->refresh()->status->name);
        $this->assertDatabaseCount('recommendation_status_histories', 4);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::SubmittedToLeader->value,
        ])
            ->assertUnprocessable();
    }

    public function test_decision_statuses_are_not_reachable_even_if_master_data_is_changed(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail()
            ->forceFill(['valid_transitions' => [RecommendationStatusEnum::Accepted->value]])
            ->save();

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::Accepted->value,
        ])
            ->assertUnprocessable();
    }

    public function test_recommendation_status_options_follow_view_policy_and_filter_decision_only_statuses(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $reporter = $this->makeUser('reporter', 'reporter@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail()
            ->forceFill([
                'valid_transitions' => [
                    RecommendationStatusEnum::InternalReview->value,
                    RecommendationStatusEnum::SubmittedToLeader->value,
                    RecommendationStatusEnum::Accepted->value,
                ],
            ])
            ->save();

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.code', 'RECS-01')
            ->assertJsonPath('data.current_status.name', RecommendationStatusEnum::Drafting->value)
            ->assertJsonFragment([
                'code' => 'RECS-02',
                'name' => RecommendationStatusEnum::InternalReview->value,
            ])
            ->assertJsonMissing([
                'name' => RecommendationStatusEnum::SubmittedToLeader->value,
            ])
            ->assertJsonMissing([
                'name' => RecommendationStatusEnum::Accepted->value,
            ]);

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', RecommendationStatusEnum::Drafting->value)
            ->assertJsonCount(0, 'data.valid_transitions');

        $this->actingAsApi($reporter);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}/status-options")
            ->assertForbidden();

        $recommendation->forceFill([
            'status_code' => RecommendationStatusModel::query()
                ->where('name', RecommendationStatusEnum::SubmittedToLeader->value)
                ->firstOrFail()
                ->code,
            'submitted_at' => now(),
        ])->save();

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}/status-options")
            ->assertOk()
            ->assertJsonPath('data.current_status.name', RecommendationStatusEnum::SubmittedToLeader->value)
            ->assertJsonCount(0, 'data.valid_transitions');
    }

    public function test_recommendation_workflow_dispatches_audit_logs_and_notifications(): void
    {
        $admin = $this->makeUser('admin', 'admin@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-satgas@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $otherSatgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => false,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $investigation = $this->makeCompletedInvestigation($case, $satgas);

        $this->actingAsApi($satgas);
        $recommendationId = $this->postJson("/api/v1/cases/{$case->id}/recommendations", $this->payload($investigation))
            ->assertCreated()
            ->json('data.id');

        $recommendation = Recommendation::query()->findOrFail($recommendationId);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecommendationCreated->value,
            'category' => AuditCategory::Recommendation->value,
            'subject_type' => $recommendation->getMorphClass(),
            'subject_id' => $recommendation->id,
        ]);

        $createdLog = AuditLog::query()
            ->where('action', AuditAction::RecommendationCreated->value)
            ->firstOrFail();

        $this->assertSame($recommendation->id, $createdLog->metadata['recommendation_id']);
        $this->assertSame(1, $admin->notifications()->where('data->notification_type_code', 'NOTIF-16')->count());
        $this->assertSame(0, $superAdmin->notifications()->where('data->notification_type_code', 'NOTIF-16')->count());
        $this->assertSame(0, $satgas->notifications()->where('data->notification_type_code', 'NOTIF-16')->count());

        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tindakan rekomendasi yang diperbarui dan tetap sensitif.',
        ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecommendationUpdated->value,
            'category' => AuditCategory::Recommendation->value,
            'subject_type' => $recommendation->getMorphClass(),
            'subject_id' => $recommendation->id,
        ]);

        $this->patchJson("/api/v1/recommendations/{$recommendation->id}/status", [
            'status' => RecommendationStatusEnum::InternalReview->value,
        ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecommendationStatusChanged->value,
            'category' => AuditCategory::Recommendation->value,
            'subject_type' => $recommendation->getMorphClass(),
            'subject_id' => $recommendation->id,
        ]);

        $this->assertSame(1, $satgas->notifications()->where('data->notification_type_code', 'NOTIF-17')->count());
        $this->assertSame(1, $otherSatgas->notifications()->where('data->notification_type_code', 'NOTIF-17')->count());
        $this->assertSame(0, $admin->notifications()->where('data->notification_type_code', 'NOTIF-17')->count());
        $this->assertSame(0, $superAdmin->notifications()->where('data->notification_type_code', 'NOTIF-17')->count());

        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")
            ->assertOk();

        $this->assertSame(0, $admin->notifications()->where('data->notification_type_code', 'NOTIF-14')->count());
        $this->assertSame(1, $superAdmin->notifications()->where('data->notification_type_code', 'NOTIF-14')->count());
        $this->assertSame(1, $satgas->notifications()->where('data->notification_type_code', 'NOTIF-17')->count());

        $this->actingAsApi($superAdmin);
        $revisionNote = 'Perbaiki dasar analisis sebelum rekomendasi dikirim kembali.';
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'return_for_revision',
            'revision_note' => $revisionNote,
        ])->assertOk();

        $this->assertSame(1, $satgas->notifications()->where('data->notification_type_code', 'NOTIF-23')->count());
        $this->assertSame(1, $otherSatgas->notifications()->where('data->notification_type_code', 'NOTIF-23')->count());

        $auditJson = AuditLog::query()
            ->whereIn('action', [
                AuditAction::RecommendationCreated->value,
                AuditAction::RecommendationUpdated->value,
                AuditAction::RecommendationStatusChanged->value,
                AuditAction::RecommendationSubmitted->value,
                AuditAction::RecommendationReturnedForRevision->value,
            ])
            ->get()
            ->toJson();

        $this->assertStringNotContainsString('Kesimpulan rekomendasi rahasia', $auditJson);
        $this->assertStringNotContainsString('Tindakan rekomendasi yang diperbarui', $auditJson);
        $this->assertStringNotContainsString($revisionNote, $auditJson);

        $payload = $satgas->notifications()->where('data->notification_type_code', 'NOTIF-17')->firstOrFail()->data;
        $this->assertSame($case->id, $payload['case_id']);
        $this->assertSame($recommendation->id, $payload['recommendation_id']);
        $this->assertArrayNotHasKey('conclusion', $payload);
        $this->assertArrayNotHasKey('recommended_actions', $payload);
    }

    public function test_only_active_super_admin_can_review_and_approval_advances_case_atomically(): void
    {
        $admin = $this->makeUser('admin', 'admin-review@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-review@university.ac.id');
        $inactiveSuperAdmin = $this->makeUser('super_admin', 'inactive-super@university.ac.id');
        $inactiveSuperAdmin->forceFill(['is_active' => false])->save();
        $satgas = $this->makeUser('satgas_ppks', 'satgas-review@university.ac.id');
        $otherSatgas = $this->makeUser('satgas_ppks', 'other-review@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($otherSatgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertForbidden();

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertOk();

        $admin->role->permissions()->syncWithoutDetaching([
            Permission::query()->where('code', 'cases.review_recommendation')->firstOrFail()->id,
        ]);
        $admin->unsetRelation('role');

        foreach ([$admin, $satgas, $inactiveSuperAdmin] as $unauthorizedReviewer) {
            $this->actingAsApi($unauthorizedReviewer);
            $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
                'action' => 'approve',
            ])->assertForbidden();
        }

        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'return_for_revision',
        ])->assertUnprocessable();
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', RecommendationStatusEnum::Accepted->value)
            ->assertJsonPath('data.leadership_review.approved_by.id', $superAdmin->id);

        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
        $this->assertNotNull($recommendation->refresh()->approved_at);
        $this->assertSame(1, $admin->notifications()->where('data->notification_type_code', 'NOTIF-24')->count());
        $this->assertSame(0, $superAdmin->notifications()->where('data->notification_type_code', 'NOTIF-24')->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::RecommendationApproved->value,
            'subject_id' => $recommendation->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CaseStatusChanged->value,
            'subject_id' => $case->id,
        ]);

        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'approve',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('recommendation_status_histories', 3);
    }

    public function test_review_detail_privacy_and_legacy_terminal_status_compatibility(): void
    {
        $admin = $this->makeUser('admin', 'admin-privacy@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-privacy@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-privacy@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertOk();

        $this->actingAsApi($superAdmin);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.conclusion', 'Kesimpulan rekomendasi rahasia.');
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'return_for_revision',
            'revision_note' => 'Catatan revisi rahasia hanya untuk reviewer dan Satgas.',
        ])->assertOk();

        $this->actingAsApi($admin);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.conclusion')
            ->assertJsonMissingPath('data.leadership_review')
            ->assertJsonMissingPath('data.revision_note');

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.leadership_review.revision_note', 'Catatan revisi rahasia hanya untuk reviewer dan Satgas.');

        $legacyStatus = RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::PartiallyAccepted->value)
            ->firstOrFail();
        $recommendation->forceFill(['status_code' => $legacyStatus->code])->save();

        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertUnprocessable();
        $this->getJson("/api/v1/recommendations/{$recommendation->id}/status-options")
            ->assertOk()
            ->assertJsonCount(0, 'data.valid_transitions');
    }

    public function test_review_metadata_is_consistent_across_return_edit_and_resubmission(): void
    {
        $admin = $this->makeUser('admin', 'admin-review-state@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-review-state@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-review-state@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertOk();

        $recommendation->forceFill([
            'approved_by' => $superAdmin->id,
            'approved_at' => now(),
        ])->save();

        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'return_for_revision',
            'revision_note' => 'Lengkapi dasar analisis sebelum rekomendasi dikirim kembali.',
        ])
            ->assertOk()
            ->assertJsonPath('data.leadership_review.approved_by', null)
            ->assertJsonPath('data.leadership_review.approved_at', null);

        $recommendation->refresh();
        $this->assertNotNull($recommendation->returned_by);
        $this->assertNotNull($recommendation->returned_at);
        $this->assertSame('Lengkapi dasar analisis sebelum rekomendasi dikirim kembali.', $recommendation->revision_note);
        $this->assertNull($recommendation->approved_by);
        $this->assertNull($recommendation->approved_at);

        $this->actingAsApi($satgas);
        $this->patchJson("/api/v1/recommendations/{$recommendation->id}", [
            'recommended_actions' => 'Tindakan rekomendasi yang diperbaiki tanpa menghapus catatan return.',
        ])->assertOk();

        $recommendation->refresh();
        $this->assertNotNull($recommendation->returned_by);
        $this->assertSame('Lengkapi dasar analisis sebelum rekomendasi dikirim kembali.', $recommendation->revision_note);

        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.leadership_review.returned_by', null)
            ->assertJsonPath('data.leadership_review.returned_at', null)
            ->assertJsonPath('data.leadership_review.revision_note', null)
            ->assertJsonPath('data.leadership_review.approved_by', null)
            ->assertJsonPath('data.leadership_review.approved_at', null);

        $recommendation->refresh();
        $this->assertNull($recommendation->returned_by);
        $this->assertNull($recommendation->returned_at);
        $this->assertNull($recommendation->revision_note);
        $this->assertNull($recommendation->approved_by);
        $this->assertNull($recommendation->approved_at);
    }

    public function test_malformed_legacy_revision_note_does_not_break_sensitive_detail_serialization(): void
    {
        $admin = $this->makeUser('admin', 'admin-legacy-note@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-legacy-note@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        DB::table('recommendations')
            ->where('id', $recommendation->id)
            ->update(['revision_note' => 'legacy plaintext that is not encrypted']);

        $this->actingAsApi($satgas);
        $this->getJson("/api/v1/recommendations/{$recommendation->id}")
            ->assertOk()
            ->assertJsonPath('data.leadership_review.revision_note', null);

        $this->assertSame(
            'legacy plaintext that is not encrypted',
            DB::table('recommendations')->where('id', $recommendation->id)->value('revision_note'),
        );
    }

    public function test_failed_approval_rolls_back_recommendation_history_audit_and_notification(): void
    {
        $admin = $this->makeUser('admin', 'admin-approval-rollback@university.ac.id');
        $superAdmin = $this->makeUser('super_admin', 'super-approval-rollback@university.ac.id');
        $satgas = $this->makeUser('satgas_ppks', 'satgas-approval-rollback@university.ac.id');
        $case = $this->makeRecommendationCase($admin, $satgas);
        $investigation = $this->makeCompletedInvestigation($case, $satgas);
        $recommendation = $this->makeRecommendation($case, $investigation, $satgas);

        $this->actingAsApi($satgas);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/submit")->assertOk();

        $decisionStatus = CaseStatus::query()->where('name', CaseStatusEnum::Decision->value)->firstOrFail();
        $case->forceFill([
            'status_code' => $decisionStatus->code,
            'current_stage' => $decisionStatus->workflow_stage,
        ])->save();
        $historyCount = $recommendation->statusHistories()->count();
        $auditCount = AuditLog::query()->count();
        $notificationCount = $admin->notifications()->count();

        $this->actingAsApi($superAdmin);
        $this->postJson("/api/v1/recommendations/{$recommendation->id}/review", [
            'action' => 'approve',
        ])->assertUnprocessable();

        $this->assertSame(RecommendationStatusEnum::SubmittedToLeader->value, $recommendation->refresh()->status->name);
        $this->assertSame(CaseStatusEnum::Decision->value, $case->refresh()->status->name);
        $this->assertSame($historyCount, $recommendation->statusHistories()->count());
        $this->assertSame($auditCount, AuditLog::query()->count());
        $this->assertSame($notificationCount, $admin->notifications()->count());
    }

    private function payload(Investigation $investigation): array
    {
        return [
            'investigation_id' => $investigation->id,
            'conclusion' => 'Kesimpulan rekomendasi rahasia.',
            'recommended_actions' => 'Tindakan rekomendasi untuk penanganan kasus.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
        ];
    }

    private function makeRecommendation(CaseRecord $case, Investigation $investigation, User $satgas): Recommendation
    {
        $status = RecommendationStatusModel::query()
            ->where('name', RecommendationStatusEnum::Drafting->value)
            ->firstOrFail();

        $recommendation = Recommendation::query()->create([
            'case_id' => $case->id,
            'investigation_id' => $investigation->id,
            'author_id' => $satgas->id,
            'status_code' => $status->code,
            'conclusion' => 'Kesimpulan rekomendasi rahasia.',
            'recommended_actions' => 'Tindakan rekomendasi untuk penanganan kasus.',
            'sanction_recommendation' => 'Rekomendasi sanksi.',
            'recovery_recommendation' => 'Rekomendasi pemulihan.',
            'prevention_recommendation' => 'Rekomendasi pencegahan.',
        ]);

        $recommendation->statusHistories()->create([
            'from_status_code' => null,
            'to_status_code' => $status->code,
            'changed_by' => $satgas->id,
            'changed_at' => now(),
        ]);

        return $recommendation;
    }

    private function makeCompletedInvestigation(CaseRecord $case, User $satgas): Investigation
    {
        return $this->makeInvestigation($case, $satgas, InvestigationStatusEnum::Completed);
    }

    private function makeInvestigation(CaseRecord $case, User $satgas, InvestigationStatusEnum $statusName): Investigation
    {
        $status = InvestigationStatus::query()
            ->where('name', $statusName->value)
            ->firstOrFail();

        return Investigation::query()->create([
            'case_id' => $case->id,
            'lead_investigator_id' => $satgas->id,
            'status_code' => $status->code,
            'plan_summary' => 'Plan investigasi rahasia.',
            'findings' => 'Temuan investigasi rahasia.',
            'conclusion' => 'Kesimpulan investigasi rahasia.',
            'started_at' => now(),
            'completed_at' => $statusName === InvestigationStatusEnum::Completed ? now() : null,
        ]);
    }

    private function makeRecommendationCase(User $admin, User $satgas): CaseRecord
    {
        return $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Recommendation);
    }

    private function makeInvestigationCase(User $admin, User $satgas): CaseRecord
    {
        return $this->makeCaseWithStatus($admin, $satgas, CaseStatusEnum::Investigation);
    }

    private function makeCaseWithStatus(User $admin, User $satgas, CaseStatusEnum $statusName): CaseRecord
    {
        $report = $this->makeReport();
        $status = CaseStatus::query()->where('name', $statusName->value)->firstOrFail();

        $case = CaseRecord::query()->create([
            'report_id' => $report->id,
            'registration_number' => $report->registration_number,
            'case_number' => 'CASE-'.now()->format('Ymd').'-'.str_pad((string) (CaseRecord::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'status_code' => $status->code,
            'priority_code' => 'PRIO-03',
            'current_stage' => $status->workflow_stage,
            'forwarded_at' => now(),
            'recommendation_at' => $statusName === CaseStatusEnum::Recommendation ? now() : null,
        ]);

        CaseAssignment::query()->create([
            'case_id' => $case->id,
            'satgas_id' => $satgas->id,
            'assigned_by' => $admin->id,
            'is_lead' => true,
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        return $case->load('status');
    }

    private function makeReport(): Report
    {
        return Report::query()->create([
            'registration_number' => 'SLP-'.now()->format('Ymd').'-'.str_pad((string) (Report::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'tracking_code' => null,
            'report_type' => 'confidential',
            'category_code' => 'RCAT-01',
            'chronology' => 'Kronologi laporan ini cukup panjang untuk dipakai sebagai data uji recommendation foundation.',
            'incident_date' => now()->toDateString(),
            'incident_time' => '10:30',
            'incident_location' => 'Gedung utama kampus lantai dua',
            'location_type' => 'LOC-01',
            'respondent_name' => 'Nama Terlapor',
            'respondent_campus_status' => 'CAMP-01',
            'respondent_relation' => 'REL-02',
            'respondent_details' => 'Detail terlapor untuk pengujian recommendation.',
            'witness_info' => 'Informasi saksi untuk pengujian recommendation.',
            'status' => ReportStatus::Forwarded->value,
            'priority' => 'PRIO-03',
            'submitted_at' => now(),
            'forwarded_at' => now(),
        ]);
    }

    private function makeUser(string $roleCode, string $email): User
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => "{$roleCode} User",
            'email' => $email,
            'password' => 'SecurePass123',
            'is_active' => true,
        ]);
    }

    private function actingAsApi(User $user): void
    {
        Sanctum::actingAs($user, ['*']);
    }
}
