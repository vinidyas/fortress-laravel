<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Imovel;
use App\Models\ImovelFoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImovelFotoService
{
    private ImageManager $imageManager;

    public function __construct(?ImageManager $imageManager = null)
    {
        $this->imageManager = $imageManager ?? new ImageManager(new Driver());
    }

    public function store(Imovel $imovel, UploadedFile $file, int $ordem, ?string $legenda = null): ImovelFoto
    {
        $storage = Storage::disk('public');
        $directory = "imoveis/{$imovel->id}/fotos";

        $image = $this->imageManager->read($file->getPathname())->orient();
        $width = $image->width();
        $height = $image->height();

        if ($width > 2560 || $height > 2560) {
            $image->scaleDown(2560, 2560);
            $width = $image->width();
            $height = $image->height();
        }

        $originalFilename = $file->getClientOriginalName();
        $mimeType = $file->getClientMimeType();
        $filename = Str::uuid()->toString().'.jpg';
        $path = "{$directory}/{$filename}";

        $encodedOriginal = (string) $image->toJpg(quality: 85);
        if ($encodedOriginal === '') {
            throw new RuntimeException('Falha ao processar a imagem do imóvel.');
        }
        $storage->put($path, $encodedOriginal);

        $thumbImage = clone $image;
        $thumbImage->scaleDown(800, 800);
        $thumbImage = $thumbImage->coverDown(400, 400);
        $thumbFilename = Str::uuid()->toString().'.webp';
        $thumbnailPath = "{$directory}/thumbs/{$thumbFilename}";
        $encodedThumb = (string) $thumbImage->toWebp(quality: 80);
        if ($encodedThumb === '') {
            throw new RuntimeException('Falha ao gerar a miniatura da imagem do imóvel.');
        }
        $storage->put($thumbnailPath, $encodedThumb);

        $size = strlen($encodedOriginal);

        return $imovel->fotos()->create([
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => $size > 0 ? $size : null,
            'ordem' => $ordem,
            'legenda' => $legenda !== null ? mb_substr(trim($legenda), 0, 255) : null,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function delete(ImovelFoto $foto): void
    {
        $paths = array_filter([$foto->path, $foto->thumbnail_path]);
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
        $foto->delete();
    }
}
