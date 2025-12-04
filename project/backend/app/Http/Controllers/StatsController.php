<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    /**
     * Get blog statistics.
     */
    public function index()
    {
        // Cache pour 5 minutes (300 secondes)
        $data = Cache::remember('stats', 300, function () {
            $totalArticles = Article::count();
            $totalComments = Comment::count();
            $totalUsers = User::count();

            $mostCommented = Article::select('articles.*', DB::raw('COUNT(comments.id) as comments_count'))
                ->leftJoin('comments', 'articles.id', '=', 'comments.article_id')
                ->groupBy('articles.id', 'articles.title', 'articles.content', 'articles.author_id', 
                          'articles.image_path', 'articles.published_at', 'articles.created_at', 'articles.updated_at')
                ->orderBy('comments_count', 'desc')
                ->limit(5)
                ->get();

            $recentArticles = Article::with('author')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return [
                'total_articles' => $totalArticles,
                'total_comments' => $totalComments,
                'total_users' => $totalUsers,
                'most_commented' => $mostCommented->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'comments_count' => $article->comments_count,
                    ];
                }),
                'recent_articles' => $recentArticles->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'title' => $article->title,
                        'author' => $article->author->name,
                        'created_at' => $article->created_at,
                    ];
                }),
            ];
        });

        return response()->json($data);
    }
}

