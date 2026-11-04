<?php

namespace App\Livewire\Cabinet;

use App\Models\Image;
use App\Services\ImageService;
use App\Services\Integrations\IntegrationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.cabinet')]
#[Title('Картинки')]
class Library extends Component
{
    use WithFileUploads, WithPagination;

    public array $uploads = [];

    public function updatedUploads(ImageService $images): void
    {
        $this->validate(['uploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240'], ['uploads.*.image' => 'Загрузите картинку JPG, PNG или WebP', 'uploads.*.max' => 'Картинка больше 10 МБ']);
        $count = 0;
        foreach ($this->uploads as $file) {
            try {
                $images->store(auth()->user(), file_get_contents($file->getRealPath()), 'upload');
                $count++;
            } catch (IntegrationException $e) {
                $this->addError('uploads', $e->getMessage());
            }
        }
        $this->uploads = [];
        if ($count) {
            session()->flash('success', 'Загружено картинок: '.$count);
        }
    }

    public function delete(int $id): void
    {
        $image = Image::where('user_id', auth()->id())->findOrFail($id);
        if ($image->id && \App\Models\Creative::where('image_id', $image->id)->exists()) {
            session()->flash('error', 'Картинка используется в кампании — её нельзя удалить');

            return;
        }
        Storage::disk('public')->delete($image->path);
        $image->delete();
    }

    public function render()
    {
        return view('livewire.cabinet.library', ['images' => auth()->user()->images()->latest('id')->paginate(24)]);
    }
}
