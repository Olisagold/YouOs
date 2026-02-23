<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Doctrine;
use App\Models\Reminder;
use App\Models\TelegramLink;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseEightConversationalCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_chat_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat', [
            'message' => 'hello',
        ])->assertUnauthorized();
    }

    public function test_doctrine_update_confirmation_flow_via_chat(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'doctrine_update',
                            'confidence' => 92,
                            'extracted_data' => $this->sampleDoctrinePayload(),
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $firstResponse = $this->postJson('/api/v1/chat', [
            'message' => 'Update my doctrine with this new structure.',
        ]);

        $firstResponse->assertOk()
            ->assertJsonPath('pending_action.action_type', 'doctrine_update');

        $conversationId = $firstResponse->json('conversation_id');

        $this->postJson('/api/v1/chat', [
            'conversation_id' => $conversationId,
            'message' => 'yes',
        ])->assertOk()
            ->assertJsonPath('pending_action', null);

        $this->assertDatabaseHas('doctrines', [
            'user_id' => $user->id,
        ]);
    }

    public function test_checkin_confirmation_flow_via_chat(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);
        CarbonImmutable::setTestNow('2026-02-23 10:30:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'checkin_start',
                            'confidence' => 90,
                            'extracted_data' => [
                                'energy' => 8,
                                'mood' => 7,
                                'missions' => ['Ship API', 'Workout', 'Read'],
                                'notes' => 'Focused day.',
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $firstResponse = $this->postJson('/api/v1/chat', [
            'message' => 'Start today checkin',
        ]);

        $firstResponse->assertOk()
            ->assertJsonPath('pending_action.action_type', 'checkin_create');

        $this->postJson('/api/v1/chat', [
            'conversation_id' => $firstResponse->json('conversation_id'),
            'message' => 'yes',
        ])->assertOk();

        $checkin = $user->dailyCheckins()->firstOrFail();

        $this->assertSame('2026-02-23', $checkin->checkin_date->toDateString());
    }

    public function test_decision_is_saved_via_chat_flow(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);
        CarbonImmutable::setTestNow('2026-02-23 09:00:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Doctrine::create([
            'user_id' => $user->id,
            ...$this->sampleDoctrinePayload(),
        ]);

        $user->dailyCheckins()->create([
            'checkin_date' => '2026-02-23',
            'energy' => 8,
            'mood' => 7,
            'missions_json' => ['Ship API', 'Walk', 'Review doctrine'],
            'notes' => 'Ready.',
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'intent' => 'decision',
                                'confidence' => 88,
                                'extracted_data' => [
                                    'category' => 'work',
                                    'context' => [
                                        'what' => 'Take a new urgent project',
                                        'why' => 'Potential growth',
                                        'when' => 'today',
                                        'urgency' => 'high',
                                        'estimated_impact' => 'High leverage but possible overload',
                                        'alternatives' => ['Delay by one sprint'],
                                    ],
                                ],
                            ]),
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'message' => [
                            'content' => json_encode([
                                'verdict' => 'delay',
                                'confidence' => 82,
                                'reasoning' => ['Current mission load is already high', 'Delay preserves doctrine priorities'],
                                'risks' => ['Execution quality drop if overloaded'],
                                'better_option' => 'Delay and re-evaluate after this week.',
                                'next_steps' => ['Protect current commitments', 'Revisit Friday'],
                            ]),
                        ],
                    ]],
                ], 200),
        ]);

        $this->postJson('/api/v1/chat', [
            'message' => 'Should I take this urgent project?',
        ])->assertOk()
            ->assertJsonPath('pending_action', null);

        $this->assertDatabaseHas('decisions', [
            'user_id' => $user->id,
            'category' => 'work',
        ]);
    }

    public function test_reminder_created_and_scheduler_sends_it(): void
    {
        config(['services.openrouter.api_key' => 'test-openrouter-key']);
        config(['services.telegram.bot_token' => 'telegram-test-token']);
        CarbonImmutable::setTestNow('2026-02-23 12:00:00');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        TelegramLink::create([
            'user_id' => $user->id,
            'code' => '123456789',
            'expires_at' => CarbonImmutable::now()->addDays(30),
            'used_at' => CarbonImmutable::now(),
            'created_at' => CarbonImmutable::now(),
        ]);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'intent' => 'reminder_create',
                            'confidence' => 93,
                            'extracted_data' => [
                                'message' => 'Review doctrine',
                                'scheduled_for' => '2026-02-23 12:05:00',
                                'timezone' => 'UTC',
                            ],
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $first = $this->postJson('/api/v1/chat', [
            'message' => 'Remind me to review doctrine at 12:05 UTC',
        ])->assertOk();

        $this->postJson('/api/v1/chat', [
            'conversation_id' => $first->json('conversation_id'),
            'message' => 'yes',
        ])->assertOk();

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'status' => Reminder::STATUS_PENDING,
        ]);

        Reminder::query()->update([
            'scheduled_for' => CarbonImmutable::now()->subMinute(),
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('youos:send-due-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reminders', [
            'user_id' => $user->id,
            'status' => Reminder::STATUS_SENT,
        ]);
    }

    public function test_telegram_webhook_secret_validation(): void
    {
        config(['services.telegram.secret_token' => 'super-secret']);

        $payload = [
            'message' => [
                'chat' => ['id' => 123456],
                'text' => 'hello',
            ],
        ];

        $this->postJson('/api/v1/telegram/webhook', $payload)
            ->assertStatus(403);

        $this->postJson('/api/v1/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ])->assertStatus(403);
    }

    public function test_nightly_export_command_creates_markdown_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'channel' => 'web',
            'external_id' => null,
        ]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Should I delay this decision?',
            'structured_json' => null,
            'created_at' => '2026-02-22 09:00:00',
        ]);

        ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Delay it and review priorities first.',
            'structured_json' => ['intent' => 'decision'],
            'created_at' => '2026-02-22 09:00:30',
        ]);

        $this->artisan('youos:export-conversations-markdown --date=2026-02-22')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists("conversations/{$user->id}/2026-02-22.md");
    }

    private function sampleDoctrinePayload(): array
    {
        return [
            'goals_json' => [
                ['rank' => 1, 'goal' => 'Execute on priorities'],
                ['rank' => 2, 'goal' => 'Preserve disciplined decisions'],
            ],
            'rules_json' => ['Do first things first', 'No impulsive commitments'],
            'habits_json' => [
                ['habit' => 'Morning planning', 'trigger' => 'After coffee'],
            ],
            'weekly_targets_json' => [
                ['target' => 'Deep work', 'metric' => 'hours', 'current' => 4, 'goal' => 20],
            ],
        ];
    }
}
