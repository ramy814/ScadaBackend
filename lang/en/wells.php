<?php

return [
    'signals' => ['marked' => 'Signals marked as read.'],
    'errors' => [
        'delete_reading' => 'You are not allowed to delete well readings.',
        'delete_inventory' => 'You are not allowed to delete diesel inventories.',
    ],
    'deleted' => [
        'reading' => 'Reading deleted (audited; the record is kept for review).',
        'inventory' => 'Inventory deleted (audited; the record is kept for review).',
        'well' => 'Well deleted.', 'team' => 'Team deleted.', 'inspector' => 'Inspector deleted.', 'photo' => 'Photo deleted.',
    ],
    'saved' => ['well' => 'Well saved.', 'team' => 'Team saved.', 'inspector' => 'Inspector saved.'],
];
