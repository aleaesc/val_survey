<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller as BaseController;

class ChatController extends BaseController
{
    public function handle(Request $request)
    {
        $data = $request->validate([
            'message' => ['required','string','min:1','max:2000'],
            'history' => ['sometimes','array'],
            'history.*.role' => ['required_with:history','in:system,user,assistant'],
            'history.*.content' => ['required_with:history','string','max:4000'],
        ]);

        // Detect if the latest user message is predominantly Tagalog (simple heuristic)
        $userMessage = (string) $data['message'];
        $preferTagalog = (bool) preg_match('/\b(ang|ng|sa|ako|ikaw|kayo|po|opo|hindi|oo|salamat|paano|saan|kailan|magkano|gusto|nasaan|bakit|kailangan|meron|wala|ito|iyan|doon|naman|ayos|pasensya|tulong)\b/i', $userMessage);
        $primaryLanguage = $preferTagalog ? 'Tagalog' : 'English';

        $system = [
            'role' => 'system',
            'content' => implode("\n", [
                'You are ValBot — a warm, upbeat, and helpful assistant for the Valenzuela City Client Satisfaction Survey. Sound like a friendly city hall staff member who explains things simply.',
                'Audience: Mixed ages including elderly. Use simple words, short sentences, and very clear steps.',
                'Adaptive language:',
                "- Default to English. If the user writes mainly in Tagalog (Filipino), switch to Tagalog as the primary language.",
                "- Keep responses concise (under 120 words by default). Use lists or short steps when helpful.",
                "- Primary language for this reply: {$primaryLanguage}.",
                'Formatting rules (critical):',
                '- Use plain text only. Do NOT use Markdown. Do NOT use **bold** or any markup.',
                '- Preserve line breaks. Separate sections with a blank line.',
                '- Prefer short bullets starting with "- " when listing items.',
                '- Example structure: Brief answer (1–2 lines) + optional bullet steps + one closing tip if needed.',
                'Goals:',
                '- Help residents complete the survey and understand questions (A1..C3).',
                '- Give step-by-step guidance when asked. Be brief and practical.',
                '- If information is unknown (e.g., office hours), say you are not sure and suggest contacting the official Valenzuela City Facebook page.',
                'Etiquette and safety:',
                '- Avoid medical, legal, or financial advice. Do not request personal data beyond the survey form.',
                '',
                'Trusted local facts (use exactly as given when relevant):',
                '- Emergency Hotlines (Valenzuela City):',
                '  • CDRRMO (City Disaster Risk Reduction & Management Office): 8352-5000, 8292-1405, 0919 009 4045, 0917 881 1639.',
                '  • Fire Station: 8292-3519.',
                '  • Police Station (Main): 8352-4000, 0906 419 7676, 0998 598 7868.',
                '  • Valenzuela City Emergency Hospital: 8352-6000.',
                '  • Valenzuela Medical Center: 8294-6711.',
                '- Government overview:',
                '  • The City’s five pillars of good governance: Education, Health, Housing, Trade & Industry, and Liveability & Disaster Preparedness.',
                '  • Examples: PLV and Valenzuela Polytechnic (free tertiary education), Central Kitchen feeding program, Barangay Health Stations, Disiplina Villages (in-city relocation), PPP projects like Valenzuela Town Center and Marulas Public Market rehabilitation.',
                '',
                'When users ask for emergency numbers or hotlines, list the numbers above clearly (bulleted) and suggest saving them. If on Tagalog, keep the numbers the same and translate the labels.',
            ]),
        ];

        // Sanitize model replies to enforce plain text formatting
        $sanitize = function ($text) {
            $t = (string) $text;
            // Normalize newlines
            $t = str_replace(["\r\n", "\r"], "\n", $t);
            // Strip common markdown emphasis and headers
            $t = str_replace(['**', '__', '`'], '', $t);
            $t = preg_replace('/^\s*#{1,6}\s*/m', '', $t) ?? $t; // remove markdown headings
            // Collapse triple newlines to at most two
            $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
            return trim($t);
        };

        // Build conversation with limited history (compatible with older Laravel Collections)
        $historyItems = collect($data['history'] ?? [])
            ->reverse()
            ->take(10)
            ->reverse()
            ->values()
            ->all();
        $systemText = (string) $system['content'];

        // Prefer OpenAI if configured; else Google Gemini; else xAI Grok; else stub
        $openKey = config('services.openai.api_key');
        $openBase = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        // Normalize to include /v1 to avoid 404s when an env omits it
        if (!str_ends_with($openBase, '/v1')) {
            $openBase .= '/v1';
        }
        $openModel = config('services.openai.model', 'gpt-4o-mini');

        if ($openKey) {
            try {
                // Map to OpenAI chat format
                $oaMessages = [ [ 'role' => 'system', 'content' => $systemText ] ];
                foreach ($historyItems as $m) {
                    if (in_array($m['role'], ['user','assistant'])) {
                        $oaMessages[] = [ 'role' => $m['role'], 'content' => (string) $m['content'] ];
                    }
                }
                $oaMessages[] = [ 'role' => 'user', 'content' => $userMessage ];

                $payload = [
                    'model' => $openModel,
                    'messages' => $oaMessages,
                    'temperature' => 0.4,
                    'max_tokens' => 512,
                ];

                $resp = Http::withHeaders([
                        'Authorization' => 'Bearer '.$openKey,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->timeout(12)->post($openBase.'/chat/completions', $payload);

                if (!$resp->ok()) {
                    Log::warning('ValBot OpenAI upstream error', [
                        'status' => $resp->status(),
                        'body' => $resp->body(),
                    ]);
                    $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
                    return response()->json([ 'provider' => 'openai', 'reply' => $fallback, 'note' => 'fallback-upstream-error', 'status' => $resp->status() ]);
                }
                $json = $resp->json();
                $reply = $json['choices'][0]['message']['content'] ?? null;
                if (!$reply) { $reply = 'Sorry, I had trouble generating a response.'; }
                return response()->json([ 'provider' => 'openai', 'reply' => $sanitize($reply) ]);
            } catch (\Throwable $e) {
                Log::warning('ValBot OpenAI exception', [ 'error' => $e->getMessage() ]);
                $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
                return response()->json([ 'provider' => 'openai', 'reply' => $fallback, 'note' => 'fallback-exception' ]);
            }
        }

        // Prefer Google Gemini when configured; else fallback to xAI Grok; else stub
        $gemKey = config('services.gemini.api_key');
        $gemBase = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $gemModel = config('services.gemini.model', 'gemini-1.5-flash');

        if ($gemKey) {
            try {
                // Map our history into Gemini contents
                $contents = [];
                foreach ($historyItems as $m) {
                    $role = $m['role'] === 'assistant' ? 'model' : ($m['role'] === 'user' ? 'user' : null);
                    if (!$role) continue; // skip system in history
                    $contents[] = [ 'role' => $role, 'parts' => [[ 'text' => (string) $m['content'] ]] ];
                }
                // Append current user turn
                $contents[] = [ 'role' => 'user', 'parts' => [[ 'text' => $userMessage ]] ];

                $payload = [
                    'system_instruction' => [ 'role' => 'system', 'parts' => [[ 'text' => $systemText ]] ],
                    'contents' => $contents,
                    'generationConfig' => [ 'temperature' => 0.4, 'maxOutputTokens' => 512 ],
                ];

                $url = $gemBase.'/models/'.$gemModel.':generateContent?key='.urlencode($gemKey);
                $resp = Http::withHeaders([ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ])->post($url, $payload);
                if (!$resp->ok()) {
                    $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
                    return response()->json([ 'provider' => 'gemini', 'reply' => $fallback, 'note' => 'fallback-upstream-error', 'status' => $resp->status() ]);
                }
                $json = $resp->json();
                $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (!$reply) {
                    $reply = 'Sorry, I had trouble generating a response.';
                }
                return response()->json([ 'provider' => 'gemini', 'reply' => $sanitize($reply) ]);
            } catch (\Throwable $e) {
                $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
                return response()->json([ 'provider' => 'gemini', 'reply' => $fallback, 'note' => 'fallback-exception' ]);
            }
        }

        // xAI Grok fallback path
        $apiKey = config('services.xai.api_key');
        $baseUrl = rtrim(config('services.xai.base_url', 'https://api.x.ai/v1'), '/');
        $model = config('services.xai.model', 'grok-beta');

        if (!$apiKey) {
            return response()->json([
                'provider' => 'stub',
                'reply' => "Hello! I’m ValBot. I can help you with the survey. An AI provider isn’t configured yet, so this is a placeholder reply.",
            ]);
        }

        // Rebuild messages for Grok format
        $messages = [ ['role' => 'system', 'content' => $systemText] ];
        foreach ($historyItems as $m) { $messages[] = ['role'=>$m['role'],'content'=>(string)$m['content']]; }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
                'max_tokens' => 512,
            ];

            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($baseUrl.'/chat/completions', $payload);

            if (!$resp->ok()) {
                // Graceful bilingual fallback so users get help even if upstream fails
                $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
                return response()->json([
                    'provider' => 'xai',
                    'reply' => $fallback,
                    'note' => 'fallback-upstream-error',
                    'status' => $resp->status(),
                ]);
            }

            $json = $resp->json();
            $reply = $json['choices'][0]['message']['content'] ?? null;
            if (!$reply) {
                $reply = 'Sorry, I had trouble generating a response.';
            }

            return response()->json([
                'provider' => 'xai',
                'reply' => $sanitize($reply),
            ]);
        } catch (\Throwable $e) {
            $fallback = "Sorry, I’m having trouble connecting to ValBot right now. Here’s a quick tip in the meantime.\n\nFor survey help, choose your answers from 1 (Very Dissatisfied) to 5 (Very Satisfied). You can add comments at the end.\n\nPasensya na, may problema sa koneksyon ngayon. Pansamantala: Pumili ng sagot mula 1 (Di Nasiyahan) hanggang 5 (Lubos na Nasiyahan). Maaari kang maglagay ng komento sa dulo.";
            return response()->json([
                'provider' => 'xai',
                'reply' => $fallback,
                'note' => 'fallback-exception',
            ]);
        }
    }
}
