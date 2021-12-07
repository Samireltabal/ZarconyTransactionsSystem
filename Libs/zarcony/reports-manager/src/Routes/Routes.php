<?php
  use Zarcony\ReportsManager\Controllers\ReportController;

  $config = [
    'prefix'        => config('reports-manager.prefix'),
    'middleware'    => config('reports-manager.middleware')
  ];

  Route::group($config, function () {

      Route::get('/', [ReportController::class, 'ping']);
      Route::get('/types', [ReportController::class, 'available_types']);
      Route::get('/list', [ReportController::class, 'list']);
      Route::post('/generate', [ReportController::class, 'generate']);
      Route::get('/show/{uuid}', [ReportController::class, 'show']);
      Route::get('/delete/{uuid}', [ReportController::class, 'delete']);

  });