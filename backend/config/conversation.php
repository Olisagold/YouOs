<?php

return [
    'llm_queue_enabled' => (bool) env('LLM_QUEUE_ENABLED', false),
    'context_message_limit' => (int) env('CHAT_CONTEXT_MESSAGE_LIMIT', 15),
    'context_char_limit' => (int) env('CHAT_CONTEXT_CHAR_LIMIT', 1200),
    'pending_action_ttl_minutes' => (int) env('CHAT_PENDING_ACTION_TTL_MINUTES', 30),
    'default_timezone' => env('CHAT_DEFAULT_TIMEZONE', 'UTC'),
];
