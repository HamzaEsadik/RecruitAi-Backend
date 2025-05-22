<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;

class ResponseService
{
    /**
     * Generate a standardized error response
     */
    public function errorResponse(string $message, \Exception $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $exception->getMessage()
        ], 500);
    }
}