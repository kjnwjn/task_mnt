<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    /** Minimal list for the assignee select. */
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->orderBy('name')->get(['id', 'name'])
        );
    }
}
