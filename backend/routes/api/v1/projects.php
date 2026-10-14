<?php

use App\Http\Controllers\Api\V1\Projects\MilestoneController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use App\Http\Controllers\Api\V1\Projects\ProjectExpenseController;
use App\Http\Controllers\Api\V1\Projects\ProjectMemberController;
use App\Http\Controllers\Api\V1\Projects\TaskCommentController;
use App\Http\Controllers\Api\V1\Projects\TaskController;
use App\Http\Controllers\Api\V1\Projects\TimeEntryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Projects & Tasks
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Policies carry the checks:
| projects.view/manage, tasks.view/manage (+ project membership),
| time_entries.manage.
*/

// Projects
Route::get('projects/overview', [ProjectController::class, 'overview'])->name('projects.overview');
Route::get('projects/{project}/summary', [ProjectController::class, 'summary'])->name('projects.summary');
Route::get('projects/{project}/time-summary', [ProjectController::class, 'timeSummary'])->name('projects.time-summary');
Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
Route::apiResource('projects', ProjectController::class);

// Members
Route::apiResource('projects.members', ProjectMemberController::class)->scoped()->except(['show']);

// Milestones (nested list/create, shallow show/update/delete)
Route::apiResource('projects.milestones', MilestoneController::class)->shallow();

// Tasks
Route::get('my-tasks', [TaskController::class, 'myTasks'])->name('tasks.mine');
Route::get('projects/{project}/tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
Route::post('tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
Route::patch('tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
Route::apiResource('tasks', TaskController::class)->only(['show', 'update', 'destroy']);

// Task comments
Route::apiResource('tasks.comments', TaskCommentController::class)->scoped()->except(['show']);

// Time tracking
Route::apiResource('time-entries', TimeEntryController::class);

// Project expenses (not posted to the GL)
Route::apiResource('projects.expenses', ProjectExpenseController::class)->scoped();
