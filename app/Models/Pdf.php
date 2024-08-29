<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Pdf extends Model implements HasMedia
{
    use HasFactory, interactsWithMedia;

    public function user(){
        return $this->belongsTo(User::class);
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')
            ->useDisk('s3');
    }

    protected $guarded = [];




}
