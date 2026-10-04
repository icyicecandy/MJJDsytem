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
                'intent' => 'greeting',
                'priority' => 10,
                'keywords' => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening'],
                'answer' => 'Hello! I can help with applications, installation schedules, support tickets, account concerns, and service questions. What do you need help with?',
            ],
            [
                'intent' => 'rejected_application',
                'priority' => 90,
                'keywords' => ['why rejected', 'application denied', 'rejected application', 'application rejected', 'rejected'],
                'answer' => 'If your application was rejected, check the reason shown in your application details. You may correct the issue and reapply when allowed.',
            ],
            [
                'intent' => 'reapply',
                'priority' => 88,
                'keywords' => ['reapply', 'apply again', 'resubmit application', 'resubmit'],
                'answer' => 'If your previous application is eligible for reapplication, you can submit a new application using the corrected information or documents.',
            ],
            [
                'intent' => 'ocr_id_reading',
                'priority' => 92,
                'keywords' => ['cannot read id', 'id not detected', 'scan id', 'ocr'],
                'answer' => 'The system may assist in reading information from the uploaded ID, but the result is only for review assistance. Please verify the extracted information before continuing.',
            ],
            [
                'intent' => 'id_upload',
                'priority' => 82,
                'keywords' => ['upload id', 'id picture', 'identity document', 'valid id photo', 'id upload'],
                'answer' => 'Upload a clear, readable image of the requested ID. Avoid glare, blur, cropped edges, or obstructed information.',
            ],
            [
                'intent' => 'application_requirements',
                'priority' => 72,
                'keywords' => ['application requirements', 'what do i need', 'required documents', 'requirements', 'documents', 'valid id'],
                'answer' => 'The application form shows the required customer and service details. Upload a clear ID image when requested and make sure its information matches your application.',
            ],
            [
                'intent' => 'duplicate_application',
                'priority' => 86,
                'keywords' => ['already applied', 'application exists', 'duplicate application', 'duplicate'],
                'answer' => 'The system may detect an existing or similar application. Please check your previous applications before submitting another one.',
            ],
            [
                'intent' => 'application_status',
                'priority' => 70,
                'keywords' => ['application status', 'track application', 'track my application', 'how do i track my application', 'tracking number', 'check application', 'pending application', 'approved application'],
                'answer' => 'You can check your application status from your account dashboard. If you have an application reference, use it to view the latest status.',
            ],
            [
                'intent' => 'apply_for_service',
                'priority' => 35,
                'keywords' => ['how to apply', 'apply for service', 'new connection', 'internet application', 'service application', 'subscribe', 'application'],
                'answer' => 'You can submit a service application from the Apply page. The form will guide you through the required customer information, service address, plan, and identity documents.',
            ],
            [
                'intent' => 'coverage_availability',
                'priority' => 76,
                'keywords' => ['available in my area', 'service area', 'coverage area', 'area covered', 'coverage', 'location available', 'what areas do you serve'],
                'answer' => 'Service availability depends on your location. Enter or confirm your service address so the system or staff can check whether your area is covered.',
            ],
            [
                'intent' => 'technician_late',
                'priority' => 94,
                'keywords' => ['technician late', 'installer not here', 'installer is not here', 'technician absent', 'installer late', 'technician did not arrive'],
                'answer' => 'If your scheduled technician has not arrived, check the installation status first. If the appointment time has passed, submit a support request so staff can review or reschedule it.',
            ],
            [
                'intent' => 'no_technician_available',
                'priority' => 91,
                'keywords' => ['no technician', 'no installer available', 'nobody assigned', 'no technician available'],
                'answer' => 'There may be no eligible technician available at the moment. Your request can remain queued until a technician matches the required schedule. Check your dashboard for updates.',
            ],
            [
                'intent' => 'reschedule_installation',
                'priority' => 89,
                'keywords' => ['reschedule installation', 'change schedule', 'another installation date', 'change appointment', 'preferred date', 'reschedule'],
                'answer' => 'You can request a schedule change from the installation or application details when that option is available. Provide the reason and your preferred date.',
            ],
            [
                'intent' => 'technician_assigned',
                'priority' => 62,
                'keywords' => ['who is assigned', 'assigned technician', 'assigned installer', 'who is my technician', 'technician'],
                'answer' => 'If a technician has been assigned, their name and the installation status appear in your application or installation details.',
            ],
            [
                'intent' => 'installation_schedule',
                'priority' => 58,
                'keywords' => ['installation schedule', 'when install', 'installation appointment', 'installation date', 'how long does installation take', 'appointment', 'installation', 'schedule'],
                'answer' => 'Approved applications are scheduled according to technician availability. Check your application details or notifications for the assigned installation date and time.',
            ],
            [
                'intent' => 'ticket_queued',
                'priority' => 93,
                'keywords' => ['waiting for technician', 'ticket not assigned', 'ticket is not assigned', 'unassigned ticket', 'ticket queued', 'queued ticket', 'queued'],
                'answer' => 'Your ticket may be waiting in the assignment queue because no eligible technician is currently available. The system will retry assignment when technicians become available.',
            ],
            [
                'intent' => 'ticket_status',
                'priority' => 84,
                'keywords' => ['ticket status', 'my ticket', 'support status', 'pending ticket', 'track ticket'],
                'answer' => 'Open Support Tickets in your account to see the current status, assigned staff, messages, and latest updates.',
            ],
            [
                'intent' => 'no_internet',
                'priority' => 96,
                'keywords' => ['no internet', 'no connection', 'internet disconnected', 'internet offline', 'disconnected'],
                'answer' => 'Check that the modem or router has power and all cables are securely connected. Restart the router once. If service does not return, create a support ticket for investigation.',
            ],
            [
                'intent' => 'slow_internet',
                'priority' => 95,
                'keywords' => ['slow internet', 'slow connection', 'internet lag', 'buffering', 'lagging'],
                'answer' => 'Restart the router and test again near it with only a few devices connected. If the slowdown continues, submit a support ticket and include when it usually occurs.',
            ],
            [
                'intent' => 'router_issue',
                'priority' => 92,
                'keywords' => ['blinking light', 'red light', 'router issue', 'modem issue', 'router', 'modem'],
                'answer' => 'Check the router power and cable connections. If an unusual warning or red indicator remains after restarting it, create a support ticket and attach a clear photo if possible.',
            ],
            [
                'intent' => 'intermittent_connection',
                'priority' => 94,
                'keywords' => ['keeps disconnecting', 'intermittent connection', 'unstable connection', 'internet unstable', 'disconnecting'],
                'answer' => 'Restart the router and observe whether the connection continues to drop. If it does, create a ticket and mention how often the disconnection happens.',
            ],
            [
                'intent' => 'create_ticket',
                'priority' => 45,
                'keywords' => ['how do i file a support ticket', 'create ticket', 'support request', 'report problem', 'report issue', 'complain', 'file ticket', 'ticket', 'issue'],
                'answer' => 'You can create a support ticket from your account. Describe the issue clearly and attach a photo when it would help the support team.',
            ],
            [
                'intent' => 'forgot_password',
                'priority' => 96,
                'keywords' => ['forgot password', 'forgot my password', 'reset password', 'password reset', 'recover password'],
                'answer' => 'Use Forgot Password on the login page and follow the instructions sent to your registered email address.',
            ],
            [
                'intent' => 'login_problem',
                'priority' => 82,
                'keywords' => ['cannot login', 'cant login', 'login failed', 'account login problem', 'unable to login'],
                'answer' => 'Check that you are using the correct email address and password. Use password recovery if needed. If you still cannot sign in, contact support.',
            ],
            [
                'intent' => 'update_profile',
                'priority' => 74,
                'keywords' => ['change name', 'change phone number', 'change address', 'update profile', 'profile settings', 'edit profile'],
                'answer' => 'You can update permitted account details from Profile Settings. Some information may require staff review depending on the change.',
            ],
            [
                'intent' => 'notification_question',
                'priority' => 55,
                'keywords' => ['email notification', 'system notification', 'notifications', 'notification', 'alert message'],
                'answer' => 'System notifications appear in your account, and important application or ticket updates may also be sent to your registered email.',
            ],
            [
                'intent' => 'no_staff_online',
                'priority' => 94,
                'keywords' => ['no agent', 'nobody online', 'staff offline', 'representative unavailable', 'no staff online', 'nobody available'],
                'answer' => 'No support staff appears to be available right now. I can still answer common questions. Create a support ticket for concerns that require staff review.',
            ],
            [
                'intent' => 'after_hours',
                'priority' => 87,
                'keywords' => ['after hours', 'office closed', 'support hours', 'outside office hours'],
                'answer' => 'Support staff may be outside their active hours. You can still submit applications and support tickets for review when staff become available.',
            ],
            [
                'intent' => 'chat_transfer',
                'priority' => 88,
                'keywords' => ['transfer chat', 'another staff member', 'technician chat', 'transfer conversation'],
                'answer' => 'If your conversation needs another staff member, it may be transferred when an appropriate staff member is available.',
            ],
            [
                'intent' => 'human_request',
                'priority' => 68,
                'keywords' => ['talk to agent', 'real person', 'human agent', 'speak to staff', 'human', 'representative'],
                'answer' => 'I can help with common questions, but I am not a human agent. If you need staff assistance, use Live Chat when available or submit a support ticket.',
            ],
            [
                'intent' => 'live_chat',
                'priority' => 52,
                'keywords' => ['chat with staff', 'live chat', 'support chat', 'chat agent'],
                'answer' => 'Use Live Chat when staff are available. If no staff member is active, I can answer common questions or you can create a support ticket for follow-up.',
            ],
            [
                'intent' => 'ai_limitations',
                'priority' => 90,
                'keywords' => ['are you ai', 'are you a bot', 'automated assistant', 'are you automated', 'bot'],
                'answer' => 'I am the system automated support assistant. I use approved support information to answer common questions and guide you to the correct feature or staff member.',
            ],
            [
                'intent' => 'emergency_urgent',
                'priority' => 85,
                'keywords' => ['service emergency', 'urgent issue', 'emergency', 'urgent', 'asap', 'immediately'],
                'answer' => 'For a service issue, create a support ticket and select the appropriate urgency. Describe the problem clearly so staff can assess it correctly.',
            ],
            [
                'intent' => 'cancellation',
                'priority' => 84,
                'keywords' => ['cancel application', 'cancel installation', 'stop work', 'cancel service request'],
                'answer' => 'Submit the cancellation or Stop Work request from the appropriate application or installation record and include the reason. Staff may need to review it.',
            ],
            [
                'intent' => 'plan_information',
                'priority' => 42,
                'keywords' => ['internet plans', 'service plans', 'available plans', 'plan price', 'package', 'internet plan', 'price', 'speed', 'plan'],
                'answer' => 'MJJD Telecom service plans are shown on the application page. Choose the plan that matches your preferred speed and budget.',
            ],
            [
                'intent' => 'support_availability',
                'priority' => 60,
                'keywords' => ['is anyone available', 'are you available', 'can i talk to support', 'can i talk to a person', 'human support', 'staff available', 'technician available'],
                'answer' => 'Support staff may be unavailable or assisting other customers. I can help with common questions while you wait, and a staff member can take over when available.',
            ],
            [
                'intent' => 'thanks',
                'priority' => 8,
                'keywords' => ['thank you', 'thanks', 'okay', 'ok'],
                'answer' => 'You are welcome! You may continue asking questions here, and support staff can assist when available.',
            ],
        ];
    }

    protected function phraseMatches(string $message, string $keyword): bool
    {
        if ($keyword === '') {
            return false;
        }

        return preg_match('/(?:^|\s)' . preg_quote($keyword, '/') . '(?=\s|$)/', $message) === 1;
    }

    protected function findProgrammedResponse(string $message): ?array
    {
        $normalized = $this->normalizeMessage($message);
        if ($normalized === '') {
            return null;
        }

        $bestRule = null;
        $bestMatches = [];
        $bestScore = -1;

        foreach ($this->programmedResponses() as $rule) {
            $matches = collect($rule['keywords'])
                ->map(fn (string $keyword) => $this->normalizeMessage($keyword))
                ->filter(fn (string $keyword) => $this->phraseMatches($normalized, $keyword))
                ->values()
                ->all();

            if ($matches === []) {
                continue;
            }

            $longestMatch = collect($matches)->sortByDesc(fn (string $keyword) => str_word_count($keyword) * 100 + strlen($keyword))->first();
            $score = (($normalized === $longestMatch) ? 10000 : 0)
                + ((int) ($rule['priority'] ?? 0) * 100)
                + (str_word_count((string) $longestMatch) * 25)
                + strlen((string) $longestMatch)
                + (count($matches) * 10);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRule = $rule;
                $bestMatches = $matches;
            }
        }

        if ($bestRule === null) {
            return null;
        }

        return [
            'source' => 'programmed',
            'answer' => $bestRule['answer'],
            'confidence' => 1.0,
            'metadata' => [
                'intent' => $bestRule['intent'],
                'matched_keywords' => $bestMatches,
            ],
        ];
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
