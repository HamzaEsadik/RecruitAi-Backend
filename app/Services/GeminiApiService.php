<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiApiService
{
    /**
     * Call Gemini API with the given prompt and schema
     */
    public function callGeminiApi(string $apiKey, string $prompt, array $schema): array
    {
        // Configure Gemini API
        $geminiEndpoint = config('services.gemini.endpoint');
        $modelId = config('services.gemini.model_id');
        $generateContentApi = config('services.gemini.generate_content_api');

        if (!$geminiEndpoint || !$modelId) {
            return [
                'success' => false,
                'message' => 'Gemini API configuration missing.'
            ];
        }

        // Send request to Gemini API
        $geminiResponse = Http::post("{$geminiEndpoint}/v1beta/models/{$modelId}:{$generateContentApi}?key={$apiKey}", [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema
            ],
        ])->json();

        // Extract data from Gemini API response
        $responseContent = null;
        if (isset($geminiResponse['candidates'][0]['content']['parts'][0]['text'])) {
            $responseContent = json_decode($geminiResponse['candidates'][0]['content']['parts'][0]['text'], true);
        }

        if (!$responseContent) {
            return [
                'success' => false,
                'message' => 'Failed to parse Gemini API response',
                'gemini_response' => $geminiResponse
            ];
        }

        return [
            'success' => true,
            'data' => $responseContent
        ];
    }
}