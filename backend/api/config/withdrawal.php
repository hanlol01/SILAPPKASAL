<?php

return [
    'early_cancellation_enabled' => (bool) env('REPORT_EARLY_CANCELLATION_ENABLED', false),
    'formal_withdrawal_enabled' => (bool) env('REPORT_FORMAL_WITHDRAWAL_ENABLED', false),
];
