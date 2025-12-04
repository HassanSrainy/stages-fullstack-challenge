<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Handle image upload with optimization.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No image provided'], 400);
        }

        $uploadedImage = $request->file('image');
        $filename = Str::random(20) . '.jpg';
        
        // Optimiser l'image avec GD natif
        $originalPath = $uploadedImage->getRealPath();
        $imageInfo = getimagesize($originalPath);
        
        // Créer l'image source selon le type
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $sourceImage = \imagecreatefromjpeg($originalPath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = \imagecreatefrompng($originalPath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = \imagecreatefromgif($originalPath);
                break;
            default:
                return response()->json(['error' => 'Unsupported image type'], 400);
        }
        
        // Dimensions originales
        $originalWidth = \imagesx($sourceImage);
        $originalHeight = \imagesy($sourceImage);
        
        // Redimensionner si trop grand (max 1200px de largeur)
        $maxWidth = 1200;
        if ($originalWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int)($originalHeight * ($maxWidth / $originalWidth));
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }
        
        // Créer la nouvelle image
        $optimizedImage = \imagecreatetruecolor($newWidth, $newHeight);
        \imagecopyresampled($optimizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // Sauvegarder avec compression 80%
        $tempPath = \sys_get_temp_dir() . '/' . $filename;
        \imagejpeg($optimizedImage, $tempPath, 80);
        
        // Libérer la mémoire
        \imagedestroy($sourceImage);
        \imagedestroy($optimizedImage);
        
        // Stocker dans Laravel Storage
        $path = 'images/' . $filename;
        Storage::disk('public')->put($path, file_get_contents($tempPath));
        unlink($tempPath);
        
        return response()->json([
            'message' => 'Image uploaded and optimized successfully',
            'path' => $path,
            'url' => '/storage/' . $path,
            'size' => Storage::disk('public')->size($path),
        ], 201);
    }

    /**
     * Delete an uploaded image.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'Image deleted successfully']);
        }

        return response()->json(['error' => 'Image not found'], 404);
    }
}

