<?php

return [
    'errors' => [
        'final' => 'This trip already has a final decision (approved or rejected) and cannot be changed.',
        'not_ready' => 'A decision requires a finished trip with confirmed uploads.',
        'manual_clarify' => 'Manual trips have no driver app session to receive a clarification request.',
        'scope' => 'You are not allowed to review trips for this vehicle in your stations.',
        'question_required' => 'Write the question for the driver before requesting clarification.',
    ],
    'decided' => [
        'approve' => 'Trip #:id approved.',
        'reject' => 'Trip #:id rejected.',
        'clarify' => 'Clarification requested from the driver for trip #:id.',
    ],
    'manual' => [
        'saved_draft' => 'Draft trip #:id saved. Review it, then submit it for approval.',
        'updated' => 'Trip #:id updated.',
        'submitted' => 'Trip #:id submitted for approval.',
        'approved' => 'Request #:id approved.',
    ],
];
