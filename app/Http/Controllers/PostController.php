<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;

class PostController extends Controller
{
    public function writeComment(Request $request, Post $post)
    {
        $token = $request->get('token');

        if (!$token) {
            return response()->json([
                'code' =>  401,
                'error' => 'Unauthorized',
                'message' => 'Token not found on request',
            ]);
        }

        try {
            $user = User::where('remember_token', $token)->firstOrFail();
        } catch (\Exception $e) {
            return response()->json([
                'code' =>  401,
                'error' => 'Unauthorized',
                'message' => 'Token missmatch',
            ]);
        }
        
        if ($post->userCanWriteComment($user)) {
            $post->comments()->create([
                'user_id' => $user->id,
                'text' => $request->get('comment')
            ]);

            $post->writer->notifications()
                ->create(['message' => "{$user->name} wrote a comment on your post {$post->title}"]);

            return response()->json([
                'code' => 200,
                'message' => 'Ok!'
            ]);
        }

        return response()->json([
            'code' => 403,
            'error' => 'Forbidden',
            'message' => "User can't write comments",
        ]);
    }

    public function getComments(Request $request, Post $post)
    {
        $comments = $post->comments()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($comment) {
                return [
                    'user_id' => $comment->user_id,
                    'comment_id' => $comment->id,
                    'login' => $comment->writer->email,
                    'premium' => $comment->writer->premium,
                    'created_at' => $comment->created_at,
                    'comment' => $comment->text,
                ];
            });

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 5);

        $comments = $comments->forPage($page, $perPage);

        return response()->json([
            'code' => 200,
            'message' => 'Ok!',
            'comments' => $comments
        ]);
    }

    public function getNotifications(Request $request)
    {
        $token = $request->get('token');

        if (!$token) {
            return response()->json([
                'code' =>  401,
                'error' => 'Unauthorized',
                'message' => 'Token not found on request',
            ]);
        }

        try {
            $user = User::where('remember_token', $token)->firstOrFail();
        } catch (\Exception $e) {
            return response()->json([
                'code' =>  401,
                'error' => 'Unauthorized',
                'message' => 'Token missmatch',
            ]);
        }

        $notifications = $user->recentNotifications()->each(function ($notification) {
            if (!! $notification->read_at) {
                return;
            }

            $notification->read_at = Carbon::now()->toDateTimeString();
            $notification->save();
        });

        return response()->json([
            'code' => 200,
            'message' => 'Ok!',
            'notifications' => $notifications->toArray()
        ]);
    }
}
