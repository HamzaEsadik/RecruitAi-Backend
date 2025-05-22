<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostRequest;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class PostController extends Controller
{
    public function index()
    {
        return Post::all();
    }

    public function store(PostRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Test if the token is valid by making a test request to the AI API
            $isTokenValid = $this->validateToken($data['token']);
            
            if (!$isTokenValid) {
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

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validate the API token by making a test request to the AI API
     *
     * @param string $token
     * @return bool
     */
    private function validateToken(string $token): bool
    {
        try {
            // Make a simple test request to the Gemini API
            $geminiEndpoint = config('services.gemini.endpoint');
            $modelId = config('services.gemini.model_id');
            $generateContentApi = config('services.gemini.generate_content_api');
            
            $response = Http::post("{$geminiEndpoint}/v1beta/models/{$modelId}:{$generateContentApi}?key={$token}", [
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

    public function show(Post $post)
    {
        return $post;
    }

    public function showShare($share)
    {
        $post = Post::where('share', $share)->firstOrFail();
        return response()->json($post);
    }

    public function showDashboard($dashboard)
    {
        $post = Post::where('dashboard', $dashboard)
            ->with(['applies.detail'])
            ->firstOrFail();
            
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }
    
    // Delete a post and its associated applies and details by dashboard identifier
    public function destroyDashboard($dashboard)
    {
        try {
            // Find the post by dashboard identifier
            $post = Post::where('dashboard', $dashboard)->firstOrFail();

            // Delete the post
            $post->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Post and all associated data deleted successfully'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the post',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
