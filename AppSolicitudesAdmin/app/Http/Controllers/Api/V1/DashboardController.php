<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reportes\ReportesService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function resumen(ReportesService $reportes): JsonResponse
    {
        return response()->json(['data' => $reportes->resumen()]);
    }
}
