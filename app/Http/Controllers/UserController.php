<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get the authenticated user
     */
    public function show(Request $request)
    {
        return new UserResource($request->user());
    }
}
