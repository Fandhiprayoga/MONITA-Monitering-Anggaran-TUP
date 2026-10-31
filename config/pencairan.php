<?php

return [
    // This source is explicitly bound to 2026, never to now()->year.
    'source_year' => (int) env('PENGAJUAN_BUDGET_YEAR', 2026),
    'source_spreadsheet_id' => env('GOOGLE_SPREADSHEET_ID', ''),
    // Configure immutable annual archives before processing historical queues.
    'archive_spreadsheet_ids' => array_filter([
        2025 => env('GOOGLE_SPREADSHEET_ID_YEAR_2025'),
        2026 => env('GOOGLE_SPREADSHEET_ID_YEAR_2026'),
    ]),
    // Enable only after verifying spreadsheet `no` is unique and never renumbered.
    'actor_source_no_stable' => (bool) env('PENGAJUAN_ACTOR_NO_STABLE', false),
    'actor_namespace' => env('PENGAJUAN_ACTOR_NAMESPACE', 'monita-users'),
    'attachment_disk' => 'local',
    'max_rows' => 500,
    'max_value' => '10000000000.00',
    'max_total' => '5000000000000.00',
];