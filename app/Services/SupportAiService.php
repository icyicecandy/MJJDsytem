<?php

namespace App\Services;

use App\Models\FaqResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SupportAiService
{
    public function quickReplies(): array
    {
        return [
            'What internet plans do you offer?',
            'How long does installation take?',
            'How do I track my application?',
            'How do I file a support ticket?',
            'What areas do you serve?',
        ];
    }

    public function findFaqMatch(string $message): ?FaqResponse
    {
        $normalized = Str::lower($message);
        $bestMatch = null;
        $bestScore = 0;

        $faqs = FaqResponse::query()->where('is_active', true)->get();

        foreach ($faqs as $faq) {
            $keywords = collect(explode(',', (string) $faq->keywords))
                ->map(fn ($keyword) => trim(Str::lower($keyword)))
                ->filter();

            $score = $keywords->reduce(function (int $carry, string $keyword) use ($normalized) {
                return $carry + (Str::contains($normalized, $keyword) ? 1 : 0);
            }, 0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $faq;
            }
        }

        return $bestScore > 0 ? $bestMatch : null;
    }

    public function generateSupportReply(User $customer, string $message): array
    {
        $faq = $this->findFaqMatch($message);
        if ($faq) {
            return [
                'source' => 'faq',
                'answer' => $faq->answer,
                'confidence' => 1.0,
                'metadata' => ['faq_id' => $faq->id],
            ];
        }

        $url = rtrim((string) config('services.ollama.url', 'http://127.0.0.1:11434'), '/');
        $model = (string) config('services.ollama.model', 'llama3.2:3b');

        try {
            $response = Http::timeout((int) config('services.ollama.timeout', 20))
                ->acceptJson()
                ->post($url . '/api/generate', [
                    'model' => $model,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.2,
                    ],
                    'prompt' => $this->buildPrompt($customer, $message),
                ]);

            if (! $response->successful()) {
                return $this->fallbackResponse();
            }

            $data = $response->json();
            $answer = trim((string) ($data['response'] ?? ''));

            if ($answer === '') {
                return $this->fallbackResponse();
            }

            if ($this->looksUncertain($answer)) {
                return $this->fallbackResponse();
            }

            return [
                'source' => 'ollama',
                'answer' => $answer,
                'confidence' => 0.65,
                'metadata' => ['model' => $model],
            ];
        } catch (\Throwable $e) {
            return $this->fallbackResponse();
        }
    }

    protected function buildPrompt(User $customer, string $message): string
    {
        return <<<PROMPT
You are MJJD AI Assistant for MJJD Telecom.

Stay strictly within these topics:
- Surf2Sawa and MJJD internet plans
- application tracking guidance
- installation scheduling guidance
- support ticket guidance
- service availability guidance within supported Luzon areas

Rules:
- Do not invent policies, pricing, timelines, or technical commitments not provided.
- If unsure, say you are not fully sure and ask the customer to wait for a human support agent.
- Keep the answer concise, professional, and business-like.
- Do not mention being a general AI model.

Known guidance:
- Customers can track applications using their reference number and registered email or mobile number.
- Customers can file tickets from the customer portal.
- Installation happens after approval and scheduling.
- Supported service review is focused on Luzon operational coverage.

Customer message:
{$message}
PROMPT;
    }

    protected function looksUncertain(string $answer): bool
    {
        $normalized = Str::lower($answer);

        return Str::contains($normalized, [
            'i do not know',
            'i don\'t know',
            'not sure',
            'cannot verify',
            'uncertain',
            'might be',
        ]);
    }

    protected function fallbackResponse(): array
    {
        return [
            'source' => 'fallback',
            'answer' => 'I\'m not fully sure about that. Our support staff are currently offline. Please leave your message and wait for an available agent.',
            'confidence' => 0.0,
            'metadata' => ['fallback' => true],
        ];
    }
}
