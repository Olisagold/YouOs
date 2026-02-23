<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportConversationsToMarkdown extends Command
{
    protected $signature = 'youos:export-conversations-markdown {--date=}';

    protected $description = 'Export previous-day conversations to markdown archive files.';

    public function handle(): int
    {
        $timezone = (string) config('conversation.default_timezone', 'UTC');
        $targetDate = $this->resolveTargetDate($timezone);
        $startLocal = CarbonImmutable::parse($targetDate, $timezone)->startOfDay();
        $endLocal = $startLocal->endOfDay();
        $startUtc = $startLocal->setTimezone('UTC');
        $endUtc = $endLocal->setTimezone('UTC');
        $exported = 0;

        User::query()
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($startUtc, $endUtc, $targetDate, &$exported): void {
                foreach ($users as $user) {
                    $conversations = $user->conversations()
                        ->whereHas('messages', function ($query) use ($startUtc, $endUtc): void {
                            $query->whereBetween('created_at', [$startUtc, $endUtc]);
                        })
                        ->with(['messages' => function ($query) use ($startUtc, $endUtc): void {
                            $query
                                ->whereBetween('created_at', [$startUtc, $endUtc])
                                ->orderBy('created_at');
                        }])
                        ->orderBy('id')
                        ->get();

                    if ($conversations->isEmpty()) {
                        continue;
                    }

                    $markdown = $this->buildMarkdown($targetDate, $conversations->all());
                    $path = sprintf('conversations/%d/%s.md', $user->id, $targetDate);
                    Storage::disk('local')->put($path, $markdown);
                    $exported++;
                }
            });

        $this->info(sprintf('Conversation exports created: %d', $exported));

        return self::SUCCESS;
    }

    private function resolveTargetDate(string $timezone): string
    {
        $option = $this->option('date');

        if (is_string($option) && trim($option) !== '') {
            return CarbonImmutable::parse($option, $timezone)->toDateString();
        }

        return CarbonImmutable::now($timezone)->subDay()->toDateString();
    }

    /**
     * @param array<int, \App\Models\Conversation> $conversations
     */
    private function buildMarkdown(string $date, array $conversations): string
    {
        $lines = [
            sprintf('# YouOS Conversation - %s', $date),
            '',
        ];

        $messageCounter = 1;

        foreach ($conversations as $conversation) {
            foreach ($conversation->messages as $message) {
                $lines[] = sprintf('## Message %d', $messageCounter);
                $lines[] = sprintf('**%s:** %s', ucfirst($message->role), (string) $message->content);

                if (is_array($message->structured_json) && $message->structured_json !== []) {
                    $lines[] = '```json';
                    $lines[] = json_encode($message->structured_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $lines[] = '```';
                }

                $lines[] = '';
                $messageCounter++;
            }
        }

        return implode("\n", $lines);
    }
}
