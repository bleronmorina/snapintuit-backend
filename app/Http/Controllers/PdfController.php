<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Models\ExtractedText;
use App\Models\Pdf;
use Aws\Textract\TextractClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[AllowDynamicProperties]
class PdfController extends Controller
{
    private $textractClient;

    public function __construct()
    {
        // Initialize the TextractClient in the constructor
        $this->textractClient = new TextractClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    }

    public function index()
    {
        $document = Pdf::with('media')->get();
        return response()->json($document, 200);
    }

    public function upload(Request $request)
    {
        set_time_limit(120);
        $request->validate([
            'file' => 'required|mimes:pdf|max:16384',
            'name' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $responseAIController = new ResponseAIController();

        if ($request->file('file')->isValid()) {
            $pdf = new Pdf();
            $pdf->user_id = $request->user_id;
            $pdf->save();

            $media = $pdf->addMedia($request->file('file'))->toMediaCollection('files', 's3');

            // Analyze document using Textract
            $text = $this->analyzeDocumentWithTextract($media->getPath());

            if ($request->name === "AI") {
                $generatedName = $responseAIController->generateDocumentName($text);
                $pdf->name = $generatedName;
            } else {
                $pdf->name = $request->name;
            }

            $generatedCategory = $responseAIController->generateDocumentCategory($text);
            $pdf->category = $generatedCategory;
            $pdf->update();

            $extractedText = new ExtractedText();
            $extractedText->pdfs_id = $pdf->id;
            $extractedText->text = $text;
            $extractedText->save();

            return response()->json([
                'message' => 'File uploaded successfully',
                'path' => $media->getFullUrl(),
                'text' => $extractedText->text,
                'id' => $pdf->id
            ], 200);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function getUserPdfs(Request $request)
    {
        Log::info('Request to get user pdfs', $request->all());
        $request->validate([
            'filter' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->input('user_id');
        $category = $request->input('filter');

        $query = Pdf::where('user_id', $userId)->with(['media'])->orderBy('created_at', 'desc');

        if ($category !== 'All') {
            $query->where('category', $category);
        }

        $pdfs = $query->get()->map(function ($pdf) {
            $media = $pdf->media->first();

            return [
                'id' => $pdf->id,
                'originalUrl' => $this->generateCustomUrl($media->getPath()),
                'title' => $pdf->name,
                'size' => isset($media) ? round(($media->size / 1024), 1) . ' Kbyte' : 'Unknown',
                'creationDate' => $pdf->created_at,
            ];
        });

        return response()->json($pdfs, 200);
    }

    private function analyzeDocumentWithTextract($s3Path)
    {
        try {
            $result = $this->textractClient->startDocumentTextDetection([
                'DocumentLocation' => [
                    'S3Object' => [
                        'Bucket' => env('AWS_BUCKET'),
                        'Name' => $s3Path,
                    ],
                ],
            ]);

            $jobId = $result['JobId'];
            do {
                $status = $this->textractClient->getDocumentTextDetection(['JobId' => $jobId]);
                $jobStatus = $status['JobStatus'];
                sleep(5);
            } while ($jobStatus == 'IN_PROGRESS');

            if ($jobStatus == 'SUCCEEDED') {
                $text = '';
                foreach ($status['Blocks'] as $block) {
                    if ($block['BlockType'] == 'LINE') {
                        $text .= $block['Text'] . "\n";
                    }
                }
                return $text;
            } else {
                return "Text extraction failed.";
            }
        } catch (\Exception $e) {
            return "An error occurred: " . $e->getMessage();
        }
    }

    public function generateCustomUrl($path)
    {
        $bucketName = 'snapintuit';
        $region = 'eu-central-1';
        return "https://{$bucketName}.s3.{$region}.amazonaws.com/{$path}";
    }

    public function deletePdf(Request $request)
    {
        $pdfId = $request->route('id');
        $pdf = Pdf::find($pdfId);

        if (auth()->user()->id !== $pdf->user_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (isset($pdf)) {
            Storage::disk('s3')->delete($pdf->media->first()->getPath());
            $pdf->delete();
            return response()->json(['message' => 'Pdf deleted successfully'], 200);
        }

        return response()->json(['message' => 'Pdf not found'], 404);
    }

    public function getPdfCategories(Request $request)
    {
        Log::info('Request to get user pdfs', $request->all());
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->input('user_id');
        $pdfs = Pdf::where('user_id', $userId)
            ->select('category')
            ->distinct()
            ->get();
        $pdfs->prepend(['category' => 'All']);

        return response()->json($pdfs, 200);
    }
}
