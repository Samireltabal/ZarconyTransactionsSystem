<?php 
  return [
    'prefix'                => env('ZARCONY_TRANSACTIONS_PREFIX', 'api/transactions'),
    'admin_prefix'          => env('ZARCONY_TRANSACTIONS_ADMIN_PREFIX', 'api/admin/transactions'),
    'general_middleware'    => ['api','auth:api'],
    'admin_middleware'      => env('ADMIN_ROLE', ['role:admin']),
    'approved_state'        => env('TRANSACTION_APPROVED_STATE', 'approved'),
    'approved_color_code'        => env('TRANSACTION_APPROVED_COLOR_CODE', 'success'),
    'pending_state'        => env('TRANSACTION_PENDING_STATE', 'pending'),
    'pending_color_code'        => env('TRANSACTION_PENDING_COLOR_CODE', 'warning'),
    'rejected_state'        => env('TRANSACTION_REJECTED_STATE', 'rejected'),
    'rejected_color_code'        => env('TRANSACTION_REJECTED_COLOR_CODE', 'error'),
    'limit_per_day'        => env('TRANSACTIONS_LIMIT_PER_DAY', 200),
  ];