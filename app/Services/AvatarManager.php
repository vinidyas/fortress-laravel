<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AvatarManager
{
    public function __construct(
        private readonly string $disk = 'public',
    ) {
    }

    public function storeForUser(User $user, UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver());

        $image = $manager->read($file->getPathname())->cover(256, 256);
        $encoded = $image->toJpeg(85);

        $path = 'avatars/'.$user->id.'/'.Str::uuid().'.jpg';

        Storage::disk($this->disk)->put($path, $encoded);

        if ($user->avatar_path && Storage::disk($this->disk)->exists($user->avatar_path)) {
            Storage::disk($this->disk)->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => $path])->save();

        return $path;
    }

    public function removeForUser(User $user): void
    {
        if (! $user->avatar_path) {
            return;
        }

        if (Storage::disk($this->disk)->exists($user->avatar_path)) {
            Storage::disk($this->disk)->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();
    }
}
