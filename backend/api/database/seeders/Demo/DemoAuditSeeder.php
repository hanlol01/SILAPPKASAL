<?php

namespace Database\Seeders\Demo;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAuditSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $events = [
        ['auth.login', 'auth'],
        ['report.created', 'report'],
        ['report.forwarded', 'report'],
        ['case.assigned', 'case'],
        ['case.status_changed', 'case'],
        ['investigation.created', 'investigation'],
        ['investigation.activity_created', 'investigation'],
        ['investigation.status_changed', 'investigation'],
        ['recommendation.created', 'recommendation'],
        ['recommendation.status_changed', 'recommendation'],
        ['decision.created', 'decision'],
        ['decision.status_changed', 'decision'],
        ['recovery.created', 'recovery'],
        ['recovery.status_changed', 'recovery'],
        ['evidence.created', 'evidence'],
        ['security.access_denied', 'security'],
        ['system.seed.demo_v2', 'system'],
    ];

    public function run(): void
    {
        $actors = User::query()->whereIn('email', [
            'superadmin@silappkasal.test',
            DemoSeed::campusEmail('admin', 'STAI-SA'),
            DemoSeed::campusEmail('satgas', 'STAI-SA'),
            DemoSeed::campusEmail('reporter', 'STAI-SA'),
        ])->get()->values();

        for ($i = 1; $i <= 120; $i++) {
            [$action, $category] = $this->events[$i % count($this->events)];
            $actor = $actors[($i - 1) % max(1, $actors->count())] ?? null;

            AuditLog::query()->updateOrCreate(
                [
                    'request_id' => sprintf('demo-request-%03d', $i),
                    'action' => $action,
                ],
                [
                    'actor_id' => $actor?->id,
                    'category' => $category,
                    'severity' => $category === 'security' ? 'warning' : 'info',
                    'subject_type' => $this->subjectType($category),
                    'subject_id' => $i,
                    'metadata' => [
                        'demo' => true,
                        'workflow_step' => $category,
                        'is_elevated_access' => false,
                    ],
                    'before_changes' => [
                        'status' => $i % 2 === 0 ? 'previous' : null,
                    ],
                    'after_changes' => [
                        'status' => 'demo_recorded',
                    ],
                    'created_at' => DemoSeed::date($i % 30),
                ]
            );
        }
    }

    private function subjectType(string $category): ?string
    {
        return match ($category) {
            'report' => 'reports',
            'case' => 'cases',
            'investigation' => 'investigations',
            'recommendation' => 'recommendations',
            'decision' => 'decisions',
            'recovery' => 'recoveries',
            'evidence' => 'evidences',
            default => null,
        };
    }
}
