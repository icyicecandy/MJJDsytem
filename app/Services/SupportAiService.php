<?php

namespace App\Services;

use App\Models\FaqResponse;
use App\Models\User;
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

    protected function normalizeMessage(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    protected function programmedResponses(): array
    {
        return [
            [
                'keywords' => ['hi', 'hello', 'good morning', 'good afternoon', 'good evening'],
                'answer' => 'Hello! I\'m the MJJD Telecom assistant. I can help with common questions while our support team is unavailable or assisting other customers.',
            ],
            [
                'keywords' => ['is anyone available', 'are you available', 'can i talk to support', 'can i talk to a person', 'human support', 'staff available', 'technician available'],
                'answer' => 'Our support team may be unavailable or assisting other customers right now. I can help with common questions while you wait, and a staff member can take over when available.',
            ],
            [
                'keywords' => ['how to apply', 'application requirements', 'requirements', 'valid id', 'service application'],
                'answer' => 'To apply, submit your contact information, service address, selected plan, and one clear photo of yourself holding your valid ID beside your face (JPG, PNG, or WebP, up to 5 MB). The admin will review serviceability before scheduling installation.',
            ],
            [
                'keywords' => ['installation', 'schedule', 'technician', 'appointment', 'when will install'],
                'answer' => 'After your application is approved, MJJD Telecom will assign a technician and schedule your installation date and time.',
            ],
            [
                'keywords' => ['track application', 'tracking number', 'check status', 'application status'],
                'answer' => 'You can track your application or support ticket using your tracking/reference number on the public tracking page.',
            ],
            [
                'keywords' => ['ticket', 'report issue', 'no internet', 'slow internet', 'connection problem'],
                'answer' => 'You can submit a support ticket with your issue details. Our staff will review it and update the ticket status.',
            ],
            [
                'keywords' => ['plan', 'internet plan', 'price', 'package', 'speed'],
                'answer' => 'MJJD Telecom offers service plans shown on the application page. Please choose the plan that matches your preferred speed and budget.',
            ],
            [
                'keywords' => ['thank you', 'thanks', 'okay', 'ok'],
                'answer' => 'You\'re welcome! You may continue asking questions here, and support staff can assist when available.',
            ],
        ];
    }

    protected function findProgrammedResponse(string $message): ?array
    {
        $normalized = $this->normalizeMessage($message);

        foreach ($this->programmedResponses() as $rule) {
            foreach ($rule['keywords'] as $keyword) {
                $normalizedKeyword = $this->normalizeMessage($keyword);

                if ($normalized === $normalizedKeyword || Str::contains($normalized, $normalizedKeyword)) {
                    return [
                        'source' => 'programmed',
                        'answer' => $rule['answer'],
                        'confidence' => 1.0,
                        'metadata' => ['keywords' => $rule['keywords']],
                    ];
                }
            }
        }

        return null;
    }

    public function findFaqMatch(string $message): ?FaqResponse
    {
        $normalized = $this->normalizeMessage($message);
        $bestMatch = null;
        $bestScore = 0;

        $faqs = FaqResponse::query()->where('is_active', true)->get();

        foreach ($faqs as $faq) {
            $keywords = collect(explode(',', (string) $faq->keywords))
                ->map(fn ($keyword) => $this->normalizeMessage($keyword))
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
        $programmed = $this->findProgrammedResponse($message);
        if ($programmed) {
            return $programmed;
        }

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
- MJJD Telecom internet plans
- application tracking guidance
- installation scheduling guidance
- support ticket guidance
- service availability guidance within supported service areas

Rules:
- Do not invent policies, pricing, timelines, or technical commitments not provided.
- If unsure, say you are not fully sure and ask the customer to wait for a human support agent.
- Keep the answer concise, professional, and business-like.
- Do not mention being a general AI model.

Known guidance:
- Customers can track applications using their reference number and registered email or mobile number.
- Customers can file tickets from the customer portal.
- Installation happens after approval and scheduling.
- Supported service review is based on serviceability assessment and slot availability.

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
            'answer' => 'I\'m not fully sure about that yet, but I can help with applications, installation schedules, tracking, tickets, and available plans. A staff member can also take over when available.',
            'confidence' => 0.0,
            'metadata' => ['fallback' => true],
        ];
    }
}
