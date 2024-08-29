<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ResponseAIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('register',[AuthController::class, 'register']);

Route::post('login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::get('pdf', [PdfController::class, 'index']);
    Route::post('upload', [PdfController::class, 'upload']);
    Route::get('user/pdfs', [PdfController::class, 'getUserPdfs']);
    Route::get('pdf/{id}', [PdfController::class, 'getPdfFile']);
    Route::get('ai', [ResponseAIController::class, 'getAiResponse']);
    Route::delete('pdf/{id}', [PdfController::class, 'deletePdf']);
});
