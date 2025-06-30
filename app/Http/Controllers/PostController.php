<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PostController extends Controller
{
    public function index()
    {
        return Post::all();
    }

    public function store(PostRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            if (!$this->validateToken($data['token'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API token. Please provide a valid token.'
                ], 401);
            }
            
            $data['access_token'] = Str::random(60);
            $data['share'] = Str::random(10);
            $data['dashboard'] = Str::random(10);

            $post = Post::create($data);

            return response()->json([
                'success' => true,
                'data' => $post
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function validateToken(string $token): bool
    {
        try {
            $geminiEndpoint = config('services.gemini.endpoint');
            $modelId = config('services.gemini.model_id');
            $generateContentApi = config('services.gemini.generate_content_api');
            
            $apiUrl = "{$geminiEndpoint}/v1beta/models/{$modelId}:{$generateContentApi}?key={$token}";

            $response = Http::post($apiUrl, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Test request to validate API token"]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 1,
                ],
            ]);
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function show(Post $post): Post
    {
        return $post;
    }

    public function showShare(string $share): JsonResponse
    {
        $post = Post::where('share', $share)->firstOrFail();
        return response()->json($post);
    }

    public function showDashboard(string $dashboard): JsonResponse
    {
        $post = Post::where('dashboard', $dashboard)
            ->with(['applies.detail'])
            ->firstOrFail();
            
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }
    
    public function destroyDashboard(string $dashboard): JsonResponse
    {
        try {
            $post = Post::where('dashboard', $dashboard)->firstOrFail();
            $post->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Post and all associated data deleted successfully.'
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the post.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
