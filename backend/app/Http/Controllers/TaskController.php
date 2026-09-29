<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $tasks = Task::query()
            ->with('assignee:id,name')
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->when($request->validated('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->validated('assigned_to'), fn (Builder $query, $assignedTo) => $query->where('assigned_to', $assignedTo))
            ->when($request->validated('search'), fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
            ->latest()
            ->orderByDesc('id')
            ->paginate($request->validated('per_page') ?? 10)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Non-admins can only create tasks for themselves; admins default to themselves.
        $data['assigned_to'] = $user->isAdmin() ? ($data['assigned_to'] ?? $user->id) : $user->id;

        $task = Task::create($data)->load('assignee:id,name');

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $data = $request->validated();

        // Non-admins cannot reassign; their tasks stay assigned to themselves.
        if (! $request->user()->isAdmin()) {
            unset($data['assigned_to']);
        }

        $task->update($data);

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}
