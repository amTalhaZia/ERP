<?php

namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Projects\StoreTaskCommentRequest;
use App\Http\Resources\Projects\TaskCommentResource;
use App\Http\Resources\Projects\UserSummary;
use App\Models\Projects\Task;
use App\Models\Projects\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends ApiController
{
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $query = $task->comments()->with('user:'.UserSummary::COLUMNS)->orderBy('created_at')->orderBy('id');

        return $this->paginated($query->paginate($this->perPage($request)), TaskCommentResource::class);
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('comment', $task);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->input('body'),
        ]);

        return (new TaskCommentResource($comment->load('user:'.UserSummary::COLUMNS)))->response()->setStatusCode(201);
    }

    public function update(StoreTaskCommentRequest $request, Task $task, TaskComment $comment): TaskCommentResource
    {
        Gate::authorize('update', $comment);

        $comment->update(['body' => $request->input('body'), 'edited_at' => now()]);

        return new TaskCommentResource($comment->load('user:'.UserSummary::COLUMNS));
    }

    public function destroy(Task $task, TaskComment $comment): JsonResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return $this->noContent();
    }
}
