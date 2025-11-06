<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\SurveySubmitted;
use App\Mail\SurveyThankYou;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;

class SurveyController extends Controller
{
    // ...existing code...

    /**
     * Store a new survey with ratings from endUser.html
     */
    public function store(Request $request)
    {
        $allowedServices = [
            'Business Permit',
            'Civil Registry',
            'Health Services',
            'Online Service',
            'Barangay Service',
            'Other',
        ];

        $allowedRatingValues = ['Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied'];

        $data = $request->validate([
            'service' => ['required','string', Rule::in($allowedServices)],
            'clientInfo' => 'nullable|array',
            'clientInfo.name' => 'nullable|string|max:255',
            'clientInfo.age' => 'nullable|string|max:255',
            'clientInfo.barangay' => 'nullable|string|max:255',
            'clientInfo.email' => 'nullable|email|max:255',
            'clientInfo.phone' => ['nullable','string','regex:/^(\+639\d{9}|09\d{9})$/'],
            'ratings' => 'required|array|min:1',
            'ratings.*' => ['nullable','string', Rule::in($allowedRatingValues)],
            'comments' => 'nullable|string',
            // Honeypot must remain empty
            'hp' => 'nullable|string|size:0',
        ]);

        // Validate rating question ids
        $allowedQuestions = ['A1','A2','A3','A4','B1','B2','C1','C2','C3'];
        $invalidKeys = [];
        foreach (array_keys($data['ratings'] ?? []) as $qid) {
            if (!in_array($qid, $allowedQuestions, true)) {
                $invalidKeys[] = $qid;
            }
        }
        if (!empty($invalidKeys)) {
            return response()->json([
                'message' => 'Invalid rating question id(s).',
                'invalid' => $invalidKeys,
            ], 422);
        }

        return DB::transaction(function () use ($data) {
            $survey = Survey::create([
                'service' => $data['service'],
                'name' => $data['clientInfo']['name'] ?? null,
                'age' => $data['clientInfo']['age'] ?? null,
                'barangay' => $data['clientInfo']['barangay'] ?? null,
                'email' => $data['clientInfo']['email'] ?? null,
                'phone' => $data['clientInfo']['phone'] ?? null,
                'comments' => $data['comments'] ?? null,
            ]);

            // Insert related ratings
            $ratingsInput = $data['ratings'] ?? [];
            $bulk = [];
            $now = now();
            foreach ($ratingsInput as $questionId => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $bulk[] = [
                    'survey_id' => $survey->id,
                    'question_id' => (string) $questionId,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($bulk)) {
                SurveyRating::insert($bulk);
            }

            // Reload with relations
            $survey->load('ratings');

            // Send email notification after commit to avoid sending on rollback
            DB::afterCommit(function () use ($survey) {
                try {
                    $to = config('mail.to.address') ?: env('SURVEY_ADMIN_EMAIL');
                    if ($to) {
                        Mail::to($to)->send(new SurveySubmitted($survey));
                    }
                    if (!empty($survey->email)) {
                        Mail::to($survey->email)->send(new SurveyThankYou($survey));
                        // Optional: also send a Web3Forms autoresponse if configured
                        $accessKey = env('WEB3FORMS_ACCESS_KEY');
                        if ($accessKey) {
                            try {
                                $msg = "Thank you for answering our survey for Valenzuela City. We truly appreciate your time and feedback.\n\nThis is an automated confirmation.";
                                // Web3Forms accepts form-like submissions; if autoresponse is enabled on the account,
                                // the 'autoresponse' field is used as the message to the submitter.
                                Http::asForm()->post('https://api.web3forms.com/submit', [
                                    'access_key'   => $accessKey,
                                    'from_name'    => 'Valenzuela City',
                                    'subject'      => 'Thank you for your feedback',
                                    // The submitter's email; Web3Forms uses this as reply-to and for autoresponse
                                    'email'        => $survey->email,
                                    'reply_to'     => 'no-reply@valenzuela.gov.ph',
                                    'message'      => 'Client Satisfaction Survey submission confirmation',
                                    'autoresponse' => $msg,
                                ]);
                            } catch (\Throwable $e) {
                                Log::warning('Web3Forms autoresponse failed', [
                                    'survey_id' => $survey->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send survey notification email', [
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            return response()->json([
                'message' => 'Survey submitted successfully',
                'survey' => $survey,
            ], 201);
        });
    }

    /**
     * List surveys with ratings (paginated).
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $surveys = Survey::with('ratings')
            ->latest()
            ->paginate($perPage);

        return response()->json($surveys);
    }

    /**
     * Show a single survey with ratings.
     */
    public function show(Survey $survey)
    {
        $survey->load('ratings');

        return response()->json($survey);
    }

    /**
     * Simple stats for dashboard.
     */
    public function stats()
    {
        $total = Survey::count();
        $latestAt = optional(Survey::latest()->first())->created_at;

        // Rating breakdown by label
        $breakdown = SurveyRating::select('value', DB::raw('COUNT(*) as count'))
            ->groupBy('value')
            ->pluck('count', 'value');

        // Unique services
        $services = Survey::select('service')
            ->distinct()
            ->pluck('service');

        // Compute averages per question and overall, mapping labels to numeric 1..5
        $valueMap = [
            'Very Dissatisfied' => 1,
            'Dissatisfied' => 2,
            'Neutral' => 3,
            'Satisfied' => 4,
            'Very Satisfied' => 5,
        ];

        $rows = SurveyRating::select('question_id', 'value', DB::raw('COUNT(*) as count'))
            ->groupBy('question_id', 'value')
            ->get();

        $questionTotals = [];
        $overallSum = 0; $overallCount = 0;
        foreach ($rows as $r) {
            $q = $r->question_id;
            $score = $valueMap[$r->value] ?? null;
            if ($score === null) continue;
            $c = (int) $r->count;
            $questionTotals[$q]['sum'] = ($questionTotals[$q]['sum'] ?? 0) + ($score * $c);
            $questionTotals[$q]['count'] = ($questionTotals[$q]['count'] ?? 0) + $c;
            $overallSum += $score * $c;
            $overallCount += $c;
        }

        $questionAverages = [];
        foreach ($questionTotals as $q => $t) {
            $questionAverages[$q] = $t['count'] > 0 ? round($t['sum'] / $t['count'], 2) : null;
        }
        $overallAverage = $overallCount > 0 ? round($overallSum / $overallCount, 2) : null;

        return response()->json([
            'total_responses' => $total,
            'latest_response_at' => $latestAt,
            'services' => $services,
            'rating_breakdown' => $breakdown,
            'question_averages' => $questionAverages,
            'overall_average' => $overallAverage,
        ]);
    }

    /**
     * Delete a single survey and its ratings.
     */
    public function destroy(Survey $survey)
    {
        return DB::transaction(function () use ($survey) {
            SurveyRating::where('survey_id', $survey->id)->delete();
            $survey->delete();
            return response()->noContent();
        });
    }

    /** Restore a soft-deleted survey (and its ratings). */
    public function restore($id)
    {
        return DB::transaction(function () use ($id) {
            $survey = Survey::withTrashed()->findOrFail($id);
            SurveyRating::withTrashed()->where('survey_id', $survey->id)->restore();
            $survey->restore();
            return response()->json(['restored' => true]);
        });
    }

    /** Export filtered surveys to CSV via Laravel Excel. */
    public function exportCsv(Request $request)
    {
        $perPage = (int) $request->query('per_page', 1000);
        $page = (int) $request->query('page', 1);
        $query = Survey::with('ratings')->latest();
        if ($svc = $request->query('service')) $query->where('service', $svc);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $rows = [];
        $ratingKeys = ['A1','A2','A3','A4','B1','B2','C1','C2','C3'];
        $headers = array_merge(['id','service','created_at','name','barangay','email','phone','comments'], $ratingKeys);
        // Build lightweight chart data (breakdown + question averages) from the export set
        $breakdown = [];
        $qTotals = [];
        $valueMap = [ 'Very Dissatisfied'=>1,'Dissatisfied'=>2,'Neutral'=>3,'Satisfied'=>4,'Very Satisfied'=>5 ];
        foreach ($paginator->items() as $r) {
            foreach ($r->ratings as $rt) {
                $breakdown[$rt->value] = ($breakdown[$rt->value] ?? 0) + 1;
                $score = $valueMap[$rt->value] ?? null;
                if ($score) {
                    $qTotals[$rt->question_id]['sum'] = ($qTotals[$rt->question_id]['sum'] ?? 0) + $score;
                    $qTotals[$rt->question_id]['count'] = ($qTotals[$rt->question_id]['count'] ?? 0) + 1;
                }
            }
        }
        if (!empty($breakdown)) {
            $rows[] = ['Rating Breakdown'];
            $rows[] = ['Label','Count'];
            foreach ($breakdown as $label=>$count) { $rows[] = [$label, $count]; }
            $rows[] = [''];
        }
        if (!empty($qTotals)) {
            $rows[] = ['Question Averages (1-5)'];
            $rows[] = ['Question','Average'];
            foreach ($ratingKeys as $k) {
                $avg = (isset($qTotals[$k]) && $qTotals[$k]['count']>0) ? round($qTotals[$k]['sum']/$qTotals[$k]['count'], 2) : '';
                $rows[] = [$k, $avg];
            }
            $rows[] = [''];
        }
        // Main detailed rows
        $rows[] = $headers;
        foreach ($paginator->items() as $r) {
            $map = [];
            foreach ($r->ratings as $rt) { $map[$rt->question_id] = $rt->value; }
            $row = [
                $r->id, $r->service, optional($r->created_at)->toDateTimeString(), $r->name, $r->barangay, $r->email, $r->phone, $r->comments,
            ];
            foreach ($ratingKeys as $k) { $row[] = $map[$k] ?? ''; }
            $rows[] = $row;
        }
        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray { public function __construct(public array $rows) {} public function array(): array { return $this->rows; } };
        $filename = 'responses_'.now()->toDateString().'.csv';
        return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, ['Content-Type' => 'text/csv']);
    }

    /** Export an analytics PDF using a Blade view rendered by Dompdf. */
    public function exportPdf(Request $request)
    {
        // Gather stats and a sample of items (limit for concise report)
        $stats = json_decode(json_encode($this->stats()->getData()), true);
        $service = $request->query('service');
        $items = Survey::with('ratings')->when($service, fn($q)=>$q->where('service',$service))->latest()->limit(200)->get();

        $html = View::make('reports.analytics', [
            'stats' => $stats,
            'items' => $items,
            'generatedAt' => now(),
            'service' => $service,
        ])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download('survey_analytics_'.now()->toDateString().'.pdf');
    }

    /**
     * Geo metrics per barangay for the admin map.
     * - participation_rate: percent of total submissions attributed to the barangay
     * - avg_score: average satisfaction score (1..5) across all ratings in the barangay
     * - top_complaint: most frequent non-stopword word in comments for the barangay (simple heuristic)
     * - total_submissions: number of surveys from the barangay
     */
    public function geoMetrics(Request $request)
    {
        $total = Survey::count();
        if ($total === 0) {
            return response()->json([]);
        }

        $surveys = Survey::with('ratings')->get();

        // Stopwords for naive keyword extraction (lowercase)
        $stop = collect([
            'the','a','an','and','or','to','of','in','on','at','for','with','without','by','from','be','is','are','was','were','it','this','that','i','we','you','they','he','she','them','our','my','your','their','not','no','yes','but','if','so','very','more','less','than','then','there','here','have','has','had','do','does','did','as','can','could','would','should','will','just','also','too','please','thank','thanks','po','opo','ako','ikaw','kami','kayo','sila','ang','ng','sa','para','pero','hindi','oo','huwag','wala','meron','yung','yung','lang','naman','daw','sana','na','pa','rin','ba','ni','si','kay','kung','ay','mga'
        ])->flip();

        $valueMap = [
            'Very Dissatisfied' => 1,
            'Dissatisfied' => 2,
            'Neutral' => 3,
            'Satisfied' => 4,
            'Very Satisfied' => 5,
        ];

        $agg = [];
        foreach ($surveys as $s) {
            $bgy = trim((string) ($s->barangay ?? 'Unknown'));
            if ($bgy === '') $bgy = 'Unknown';
            $a = $agg[$bgy] ?? [
                'total' => 0,
                'score_sum' => 0,
                'score_count' => 0,
                'words' => [],
            ];
            $a['total'] += 1;
            foreach ($s->ratings as $rt) {
                $score = $valueMap[$rt->value] ?? null;
                if ($score) { $a['score_sum'] += $score; $a['score_count'] += 1; }
            }
            $txt = strtolower((string) ($s->comments ?? ''));
            if ($txt) {
                // tokenize by non-letters, keep simple words
                $parts = preg_split('/[^a-zñáéíóúü]+/u', $txt, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parts as $w) {
                    if (strlen($w) < 4) continue;
                    if (isset($stop[$w])) continue;
                    $a['words'][$w] = ($a['words'][$w] ?? 0) + 1;
                }
            }
            $agg[$bgy] = $a;
        }

        $out = [];
        foreach ($agg as $bgy => $a) {
            $rate = round(($a['total'] / $total) * 100, 1);
            $avg = $a['score_count'] > 0 ? round($a['score_sum'] / $a['score_count'], 2) : null;
            arsort($a['words']);
            $top = $a['words'] ? array_key_first($a['words']) : null;
            $out[$bgy] = [
                'participation_rate' => $rate,
                'avg_score' => $avg,
                'top_complaint' => $top,
                'total_submissions' => $a['total'],
            ];
        }

        return response()->json($out);
    }
}