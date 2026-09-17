<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    public function index()
    {
        $photos = Photo::latest()->get();

        return view('booth', ['photos' => $photos]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        // Expect a data URL like: data:image/png;base64,AAAA...
        $data = $request->input('image');

        if (! preg_match('/^data:image\/(png|jpeg);base64,/', $data, $matches)) {
            return response()->json(['message' => 'Invalid image data.'], 422);
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $base64 = substr($data, strpos($data, ',') + 1);
        $binary = base64_decode($base64);

        if ($binary === false) {
            return response()->json(['message' => 'Could not decode image.'], 422);
        }

        $filename = 'photos/'.now()->format('Ymd_His').'_'.Str::random(6).'.'.$extension;
        Storage::disk('public')->put($filename, $binary);

        $photo = Photo::create(['path' => $filename]);

        return response()->json([
            'id' => $photo->id,
            'url' => $photo->url,
        ]);
    }

    public function destroy(Photo $photo)
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->json(['deleted' => true]);
    }
}
