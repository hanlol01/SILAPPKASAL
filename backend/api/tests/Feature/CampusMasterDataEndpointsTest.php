<?php

namespace Tests\Feature;

use App\Models\University;
use Database\Seeders\CampusMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampusMasterDataEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CampusMasterDataSeeder::class);
    }

    public function test_universities_endpoint_is_public_active_only_and_privacy_safe(): void
    {
        University::query()->where('code', 'DEMO-ST')->update(['is_active' => false]);

        $this->getJson('/api/v1/universities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(8, 'data')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.hotline');

        $this->getJson('/api/v1/universities')
            ->assertJsonFragment(['code' => 'STAI-SA'])
            ->assertJsonFragment(['code' => 'IMA-BJR'])
            ->assertJsonFragment(['code' => 'DEMO-UNIV'])
            ->assertJsonMissing(['code' => 'DEMO-ST']);
    }

    public function test_faculties_requires_university_id_and_returns_filtered_data(): void
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $college = University::query()->where('code', 'DEMO-ST')->firstOrFail();

        $this->getJson('/api/v1/faculties')->assertUnprocessable();

        $this->getJson("/api/v1/faculties?university_id={$university->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.university_id', $university->id);

        $this->getJson("/api/v1/faculties?university_id={$college->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_study_programs_requires_university_id_and_filters_by_faculty(): void
    {
        $university = University::query()->with('faculties')->where('code', 'DEMO-UNIV')->firstOrFail();
        $faculty = $university->faculties->firstWhere('code', 'FT');

        $this->getJson('/api/v1/study-programs')->assertUnprocessable();

        $this->getJson("/api/v1/study-programs?university_id={$university->id}")
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->getJson("/api/v1/study-programs?university_id={$university->id}&faculty_id={$faculty->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.faculty_id', $faculty->id);
    }

    public function test_study_programs_validates_faculty_belongs_to_university(): void
    {
        $university = University::query()->where('code', 'DEMO-UNIV')->firstOrFail();
        $college = University::query()->where('code', 'DEMO-ST')->firstOrFail();
        $faculty = $university->faculties()->firstOrFail();

        $this->getJson("/api/v1/study-programs?university_id={$college->id}&faculty_id={$faculty->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['faculty_id']);
    }

    public function test_universities_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/v1/universities')->assertOk();
        }

        $this->getJson('/api/v1/universities')->assertTooManyRequests();
    }
}
