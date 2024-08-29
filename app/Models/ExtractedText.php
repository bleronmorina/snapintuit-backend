<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractedText extends Model
{
    use HasFactory;

    public function pdf(){
        return $this -> belongsTo(Pdf::class);
    }

    protected $fillable = [
        'pdfs_id',
        'text'
    ];
}
