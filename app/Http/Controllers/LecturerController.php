<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use Illuminate\Http\Request;

class LecturerController extends Controller
{
    public function index()
    {
        return response()->json(Lecturer::orderBy('last_name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
        ]);

        return response()->json(Lecturer::create($validated), 201);
    }

    public function update(Request $request, Lecturer $lecturer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
        ]);
        $lecturer->update($validated);
        return response()->json($lecturer);
    }

    public function destroy(Lecturer $lecturer)
    {
        $lecturer->delete();
        return response()->json(null, 204);
    }
}