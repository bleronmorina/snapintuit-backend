<?php

namespace App\Http\Controllers;

use App\Models\AIResponse;
use App\Models\ExtractedText;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ResponseAIController extends Controller
{
    public function getAiResponse(Request $request)
    {
        $request->validate([
            'pdfs_id' => 'required|integer|exists:extracted_texts,pdfs_id',
            'prompt' => 'required|string',
        ]);

        $pdfId = $request->input('pdfs_id');
        $prompt = $request->input('prompt');

        $extractedText = ExtractedText::where('pdfs_id', $pdfId)->firstOrFail();

        $response = $this->sendToOpenAI($extractedText->text, $prompt, 150);

        $aiResponse = new AIResponse();
        $aiResponse->pdfs_id = $pdfId;
        $aiResponse->prompt = $prompt;
        $aiResponse->response =data_get($response, 'content');
        $aiResponse->save();

        return response()->json($response, 200);
    }

    public function generateDocumentName($text)
    {
        $prompt = 'Generate a title based on the following text (only return the simple name,no apostrophes, keep it extra short!): ' . $text;

        $response = $this->sendToOpenAI("", $prompt, 10);

        return data_get($response, 'content');
    }

    public function generateDocumentCategory($text)
    {
        $prompt = "Given the following text from a document, generate a single, simple category name that best
        describes the content. The category should be one word and reflect the main theme or subject of the
        text. Possible categories include 'Contract', 'Book', 'Report', 'Article', 'Invoice', 'Manual' 'Letter'
        and others. Ensure your answer is only a plain text word that captures the essence of the document.
        Here is the text:  " . $text;

        $response = $this->sendToOpenAI("", $prompt, 10);

        return data_get($response, 'content');
    }



    private function sendToOpenAI($text, $prompt, $tokens)
    {
        $client = new Client();

        $apiKey = env('OPENAI_API_KEY');
        $apiUrl = 'https://api.openai.com/v1/chat/completions';

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant.',
            ],
            [
                'role' => 'user',
                'content' => $text . "\n" . $prompt,
            ]
        ];

        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => $tokens ?? 100,
        ];

        try {
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            if (isset($responseBody['choices'][0]['message']['content'])) {
                return ['content'=>$responseBody['choices'][0]['message']['content']];
            } else {
                throw new \Exception('Content not found in the API response.');
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
