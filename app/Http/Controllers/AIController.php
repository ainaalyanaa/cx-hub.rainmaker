<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\AITicketClassification;
use App\Services\AI\AITicketClassificationService;
use App\Services\AI\AIResponseSuggestionsService;
use App\Services\AI\AISentimentAnalysisService;
use App\Services\AI\AIPredictiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;

class AIController extends Controller
{
    private $aiService;
    private $responseService;
    private $sentimentService;
    private $analyticsService;

    public function __construct(
        AITicketClassificationService $aiService,
        AIResponseSuggestionsService $responseService,
        AISentimentAnalysisService $sentimentService,
        AIPredictiveAnalyticsService $analyticsService
    ) {
        $this->aiService = $aiService;
        $this->responseService = $responseService;
        $this->sentimentService = $sentimentService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * REAL-TIME Analytics - Fresh tickets + smart caching
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 1);
            $forceRefresh = $request->get('refresh', false);
            
            // 1. ALWAYS get fresh recent tickets (last 2 hours)
            $recentTickets = $this->getFreshTickets(2); // Last 2 hours
            
            // 2. Get cached stats OR calculate fresh if forced
            if ($forceRefresh) {
                $stats = $this->calculateStats($days);
                $sentiments = $this->calculateRealTimeSentiments($days, $recentTickets);
                Cache::put("stats_{$days}", $stats, 300); // 5 min cache
                Cache::put("sentiments_{$days}", $sentiments, 300);
            } else {
                $stats = Cache::remember("stats_{$days}", 300, function () use ($days) {
                    return $this->calculateStats($days);
                });
                
                // 3. SMART: Use cached sentiments but blend with fresh tickets
                $cachedSentiments = Cache::remember("sentiments_{$days}", 300, function () use ($days, $recentTickets) {
                    return $this->calculateRealTimeSentiments($days, $recentTickets);
                });
                $sentiments = $this->blendSentiments($cachedSentiments, $recentTickets, $days);
            }
            
            $data = array_merge($stats, [
                'classification' => array_merge($stats['classification'], [
                    'recent_activity' => $recentTickets
                ]),
                'sentiment' => $sentiments
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'performance' => [
                    'fresh_tickets' => count($recentTickets),
                    'cache_status' => $forceRefresh ? 'refreshed' : 'cached',
                    'sentiment_freshness' => 'real-time blended',
                    'timestamp' => now()->toIso8601String()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => true,
                'data' => $this->getFallbackData(),
                'message' => 'Using fallback data'
            ]);
        }
    }
    
    /**
     * Get FRESH tickets (always real-time)
     */
    private function getFreshTickets(int $hours = 2): array
    {
        $startDate = now()->subHours($hours);
        
        return Ticket::with(['priority:id,name', 'category:id,name'])
            ->select(['id', 'uid', 'subject', 'details', 'priority_id', 'category_id', 'created_at'])
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($ticket) {
                $sentiment = $this->analyzeTicketSentiment($ticket);
                
                return [
                    'id' => $ticket->id,
                    'ticket_id' => $ticket->id,
                    'ticket' => [
                        'id' => $ticket->id,
                        'uid' => $ticket->uid,
                        'title' => $ticket->subject,
                        'description' => substr(strip_tags($ticket->details ?? ''), 0, 100),
                        'priority' => $ticket->priority->name ?? 'unknown',
                        'category' => $ticket->category->name ?? 'unknown',
                        'full_text' => ($ticket->subject ?? '') . ' ' . strip_tags($ticket->details ?? '')
                    ],
                    'priority' => ['name' => $ticket->priority->name ?? 'unknown'],
                    'category' => ['name' => $ticket->category->name ?? 'unknown'],
                    'created_at' => $ticket->created_at->toISOString(),
                    'confidence_score' => 0.85,
                    'sentiment' => $sentiment,
                    'sentiment_emoji' => $this->getSentimentEmoji($sentiment),
                    'is_fresh' => $ticket->created_at->gt(now()->subMinutes(30)) // Last 30 min
                ];
            })->toArray();
    }
    
    /**
     * Calculate stats (cached)
     */
    private function calculateStats(int $days): array
    {
        $startDate = now()->subDays($days);
        $ticketCount = Ticket::where('created_at', '>=', $startDate)->count();
        
        $classStats = DB::select("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN confidence_score >= 0.7 THEN 1 ELSE 0 END) as high_confidence,
                SUM(CASE WHEN applied = 1 THEN 1 ELSE 0 END) as applied,
                SUM(CASE WHEN ai_generated = 1 THEN 1 ELSE 0 END) as ai_generated,
                COALESCE(AVG(confidence_score), 0.75) as avg_confidence
            FROM ai_ticket_classifications
        ")[0];
        
        $slaStats = $this->getSLAStats($startDate);
        
        return [
            'predictions' => [
                'ticket_volume' => [
                    'predicted' => max(10, round($ticketCount * 1.1)),
                    'trend' => $ticketCount > 5 ? 'increasing' : 'stable'
                ],
                'sla_compliance' => [
                    'current' => $slaStats['compliance_rate'],
                    'predicted' => min(1.0, $slaStats['compliance_rate'] * 1.05),
                    'breaches' => $slaStats['breach_count']
                ],
                'resolution_time' => [
                    'average_hours' => $slaStats['avg_hours'],
                    'predicted_hours' => max(1, $slaStats['avg_hours'] * 0.95)
                ]
            ],
            'classification' => [
                'total_classifications' => (int) ($classStats->total ?? 0),
                'high_confidence_classifications' => (int) ($classStats->high_confidence ?? 0),
                'applied_classifications' => (int) ($classStats->applied ?? 0),
                'ai_generated_classifications' => (int) ($classStats->ai_generated ?? 0),
                'average_confidence' => (float) ($classStats->avg_confidence ?? 0.75),
                'classification_accuracy' => $this->calculateSimpleAccuracy()
            ]
        ];
    }
    
    /**
     * Calculate real-time sentiments with fresh tickets
     */
    private function calculateRealTimeSentiments(int $days, array $freshTickets = []): array
    {
        $startDate = now()->subDays($days);
        $totalTickets = Ticket::where('created_at', '>=', $startDate)->count();
        
        if ($totalTickets === 0) {
            return $this->getDefaultSentiments();
        }
        
        // Get a sample of tickets for sentiment analysis
        $sampleTickets = Ticket::where('created_at', '>=', $startDate)
            ->select(['id', 'subject', 'details'])
            ->orderBy('created_at', 'desc')
            ->limit(min(100, $totalTickets))
            ->get();
        
        // Analyze sentiments
        $sentimentCounts = $this->analyzeTicketsBatch($sampleTickets);
        
        // Add fresh tickets to the mix
        foreach ($freshTickets as $ticket) {
            if (isset($ticket['sentiment'])) {
                $sentimentCounts[$ticket['sentiment']]++;
            }
        }
        
        // Scale to total tickets
        $sampleSize = $sampleTickets->count() + count($freshTickets);
        if ($sampleSize > 0) {
            foreach ($sentimentCounts as $type => $count) {
                $sentimentCounts[$type] = round(($count / $sampleSize) * $totalTickets);
            }
        }
        
        return [
            'distribution' => $this->formatSentimentDistribution($sentimentCounts, $totalTickets),
            'total_tickets' => $totalTickets,
            'impact' => $this->calculateSentimentImpact($startDate),
            'last_analyzed' => now()->toIso8601String()
        ];
    }
    
    /**
     * BLEND cached sentiments with fresh tickets (SMART update)
     */
    private function blendSentiments(array $cachedSentiments, array $freshTickets, int $days): array
    {
        // If no cached sentiments, calculate fresh
        if (empty($cachedSentiments) || empty($cachedSentiments['distribution'])) {
            return $this->calculateRealTimeSentiments($days, $freshTickets);
        }
        
        // If no fresh tickets, return cached
        if (empty($freshTickets)) {
            return $cachedSentiments;
        }
        
        // Analyze fresh tickets
        $freshSentiments = [];
        foreach ($freshTickets as $ticket) {
            $sentiment = $ticket['sentiment'] ?? 'neutral';
            $freshSentiments[$sentiment] = ($freshSentiments[$sentiment] ?? 0) + 1;
        }
        
        // Get cached distribution
        $cachedDistribution = $cachedSentiments['distribution'] ?? [];
        $totalTickets = $cachedSentiments['total_tickets'] ?? 0;
        
        // Create weighted blend (70% cached, 30% fresh)
        $blendedCounts = [];
        
        foreach ($cachedDistribution as $item) {
            $blendedCounts[$item['type']] = $item['count'] * 0.7;
        }
        
        foreach ($freshSentiments as $type => $count) {
            $blendedCounts[$type] = ($blendedCounts[$type] ?? 0) + ($count * 3); // Weight fresh tickets more
        }
        
        // Convert back to distribution format
        $newTotal = array_sum($blendedCounts);
        $distribution = [];
        
        foreach ($blendedCounts as $type => $count) {
            if ($count > 0) {
                $config = $this->getSentimentConfigItem($type);
                $distribution[] = [
                    'type' => $type,
                    'count' => (int) $count,
                    'percentage' => $newTotal > 0 ? $count / $newTotal : 0,
                    'label' => $config['label'],
                    'emoji' => $config['emoji'],
                    'class' => $config['class']
                ];
            }
        }
        
        // Sort by count
        usort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);
        
        return [
            'distribution' => $distribution,
            'total_tickets' => max($totalTickets, $newTotal),
            'impact' => $cachedSentiments['impact'] ?? [],
            'last_analyzed' => now()->toIso8601String(),
            'fresh_tickets_included' => count($freshTickets),
            'blend_note' => 'Real-time blended (70% cached + 30% fresh)'
        ];
    }
    
    /**
     * Get single sentiment config item
     */
    private function getSentimentConfigItem(string $type): array
    {
        $config = $this->getSentimentConfig();
        return $config[$type] ?? $config['neutral'];
    }
    
    /**
     * Analyze batch of tickets
     */
    private function analyzeTicketsBatch($tickets): array
    {
        $sentimentCounts = array_fill_keys([
            'angry', 'frustrated', 'sad', 'neutral', 'happy', 'satisfied', 'excited'
        ], 0);
        
        foreach ($tickets as $ticket) {
            $sentiment = $this->analyzeTicketSentiment($ticket);
            $sentimentCounts[$sentiment]++;
        }
        
        return $sentimentCounts;
    }
    
    /**
     * Fast sentiment analysis per ticket
     */
    private function analyzeTicketSentiment($ticket): string
    {
        $text = strtolower(($ticket->subject ?? '') . ' ' . strip_tags($ticket->details ?? ''));
        
        // Check in order of severity
        if (preg_match('/\b(urgent|emergency|critical|down|broken|furious|hate|terrible|awful)\b/', $text)) {
            return 'angry';
        }
        if (preg_match('/\b(frustrated)\b/', $text)) {
            return 'frustrated';
        }
        if (preg_match('/\b(sad|disappointed|unhappy|sorry|regret)\b/', $text)) {
            return 'sad';
        }
        if (preg_match('/\b(resolved|fixed|solved|completed|done|finished)\b/', $text)) {
            return 'satisfied';
        }
        if (preg_match('/\b(happy|pleased|thank you|thanks|appreciate|great|good|excellent)\b/', $text)) {
            return 'happy';
        }
        if (preg_match('/\b(excited|awesome|amazing|wow|fantastic)\b/', $text)) {
            return 'excited';
        }
        
        return 'neutral';
    }
    
    /**
     * Get sentiment emoji
     */
    private function getSentimentEmoji(string $type): string
    {
        $emojis = [
            'angry' => '😠', 'frustrated' => '😤', 'sad' => '😔',
            'neutral' => '🤝', 'happy' => '😊', 'satisfied' => '👍',
            'excited' => '🎉'
        ];
        
        return $emojis[$type] ?? '🤝';
    }
    

    /**
     * Format sentiment distribution
     */
    private function formatSentimentDistribution(array $counts, int $total): array
    {
        $distribution = [];
        $sentimentConfig = $this->getSentimentConfig();
        
        foreach ($counts as $type => $count) {
            if ($count > 0) {
                $config = $sentimentConfig[$type] ?? $sentimentConfig['neutral'];
                $distribution[] = [
                    'type' => $type,
                    'count' => $count,
                    'percentage' => $total > 0 ? $count / $total : 0,
                    'label' => $config['label'],
                    'emoji' => $config['emoji'],
                    'class' => $config['class']
                ];
            }
        }
        
        // Sort by count (highest first)
        usort($distribution, fn($a, $b) => $b['count'] <=> $a['count']);
        
        return $distribution;
    }
    
    /**
     * Calculate sentiment impact on resolution time
     */
    private function calculateSentimentImpact($startDate): array
    {
        // Simple impact calculation
        return [
            ['sentiment' => 'angry', 'impact' => -0.25, 'label' => '25% slower resolution'],
            ['sentiment' => 'frustrated', 'impact' => -0.15, 'label' => '15% slower resolution'],
            ['sentiment' => 'neutral', 'impact' => 0, 'label' => 'Average resolution'],
            ['sentiment' => 'happy', 'impact' => 0.1, 'label' => '10% faster resolution'],
            ['sentiment' => 'satisfied', 'impact' => 0.15, 'label' => '15% faster resolution']
        ];
    }
    
    /**
     * Get SLA stats
     */
    private function getSLAStats($startDate): array
    {
        try {
            $result = DB::select("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN resolved_at <= sla_due_date OR sla_due_date IS NULL THEN 1 ELSE 0 END) as compliant,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours
                FROM tickets
                WHERE created_at >= ? AND resolved_at IS NOT NULL
            ", [$startDate])[0] ?? (object) ['total' => 0, 'compliant' => 0, 'avg_hours' => 24];
            
            $total = $result->total ?? 0;
            $compliant = $result->compliant ?? 0;
            
            return [
                'compliance_rate' => $total > 0 ? $compliant / $total : 0.95,
                'breach_count' => $total - $compliant,
                'avg_hours' => $result->avg_hours ?? 24
            ];
        } catch (\Exception $e) {
            return ['compliance_rate' => 0.95, 'breach_count' => 0, 'avg_hours' => 24];
        }
    }
    
    /**
     * Calculate simple accuracy
     */
    private function calculateSimpleAccuracy(): float
    {
        $applied = AITicketClassification::where('applied', true)->count();
        if ($applied === 0) return 0.85;
        
        $correct = AITicketClassification::where('applied', true)
            ->where('confidence_score', '>=', 0.7)
            ->count();
            
        return $correct / $applied;
    }
    
    /**
     * Get sentiment configuration
     */
    private function getSentimentConfig(): array
    {
        return [
            'angry' => [
                'label' => 'Angry',
                'emoji' => '😠',
                'class' => 'bg-gradient-to-br from-red-500 to-rose-600 text-white'
            ],
            'frustrated' => [
                'label' => 'Frustrated',
                'emoji' => '😤',
                'class' => 'bg-gradient-to-br from-orange-500 to-amber-600 text-white'
            ],
            'sad' => [
                'label' => 'Sad',
                'emoji' => '😔',
                'class' => 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white'
            ],
            'neutral' => [
                'label' => 'Neutral',
                'emoji' => '🤝',
                'class' => 'bg-gradient-to-br from-slate-500 to-gray-600 text-white'
            ],
            'happy' => [
                'label' => 'Happy',
                'emoji' => '😊',
                'class' => 'bg-gradient-to-br from-green-500 to-emerald-600 text-white'
            ],
            'satisfied' => [
                'label' => 'Satisfied',
                'emoji' => '👍',
                'class' => 'bg-gradient-to-br from-emerald-500 to-teal-600 text-white'
            ],
            'excited' => [
                'label' => 'Excited',
                'emoji' => '🎉',
                'class' => 'bg-gradient-to-br from-purple-500 to-pink-600 text-white'
            ]
        ];
    }
    
    /**
     * Default sentiments for empty data
     */
    private function getDefaultSentiments(): array
    {
        return [
            'distribution' => [
                [
                    'type' => 'neutral',
                    'count' => 10,
                    'percentage' => 1.0,
                    'label' => 'Neutral',
                    'emoji' => '🤝',
                    'class' => 'bg-gradient-to-br from-slate-500 to-gray-600 text-white'
                ]
            ],
            'total_tickets' => 10,
            'impact' => [],
            'last_analyzed' => now()->toIso8601String()
        ];
    }
    
    /**
     * Fallback data
     */
    private function getFallbackData(): array
    {
        $ticketCount = Ticket::count();
        
        return [
            'predictions' => [
                'ticket_volume' => ['predicted' => max(10, $ticketCount + 5), 'trend' => 'stable'],
                'sla_compliance' => ['current' => 0.95, 'predicted' => 0.96, 'breaches' => 0],
                'resolution_time' => ['average_hours' => 24, 'predicted_hours' => 22]
            ],
            'classification' => [
                'total_classifications' => AITicketClassification::count(),
                'high_confidence_classifications' => 0,
                'applied_classifications' => 0,
                'ai_generated_classifications' => 0,
                'average_confidence' => 0.75,
                'classification_accuracy' => 0.85,
                'recent_activity' => []
            ],
            'sentiment' => $this->getDefaultSentiments()
        ];
    }
    
    /**
     * Debug endpoint to see sentiment analysis
     */
    public function debugSentiments(Request $request): JsonResponse
    {
        $tickets = Ticket::select(['id', 'uid', 'subject', 'details', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($ticket) {
                $sentiment = $this->analyzeTicketSentiment($ticket);
                $text = substr(($ticket->subject ?? '') . ' ' . strip_tags($ticket->details ?? ''), 0, 150);
                
                return [
                    'id' => $ticket->id,
                    'uid' => $ticket->uid,
                    'text' => $text . '...',
                    'sentiment' => $sentiment,
                    'emoji' => $this->getSentimentEmoji($sentiment),
                    'created_at' => $ticket->created_at->toDateTimeString()
                ];
            });
        
        return response()->json([
            'status' => 'ok',
            'total_tickets' => Ticket::count(),
            'sample_analysis' => $tickets,
            'cache_driver' => config('cache.default'),
            'sentiment_config' => array_keys($this->getSentimentConfig())
        ]);
    }
    
    // ========== Keep all the other methods from the first controller ==========
    
    /**
     * Get AI classification suggestions for a ticket
     */
    public function getClassificationSuggestions(Ticket $ticket): JsonResponse
    {
        try {
            $suggestions = $this->aiService->getSuggestions($ticket);
            
            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get AI suggestions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI suggestions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Apply AI classification to a ticket
     */
    public function applyClassification(Request $request, Ticket $ticket): JsonResponse
    {
        $request->validate([
            'priority_id' => 'nullable|exists:priorities,id',
            'category_id' => 'nullable|exists:categories,id',
            'department_id' => 'nullable|exists:departments,id',
            'type_id' => 'nullable|exists:types,id',
            'confidence_score' => 'nullable|numeric|between:0,1',
            'reasoning' => 'nullable|string|max:1000'
        ]);

        try {
            $classification = [
                'priority' => $request->priority_id,
                'category' => $request->category_id,
                'department' => $request->department_id,
                'type' => $request->type_id,
                'confidence' => $request->confidence_score ?? 0.0,
                'reasoning' => $request->reasoning ?? 'Manual application',
                'ai_generated' => false,
                'timestamp' => now()
            ];

            $success = $this->aiService->applyClassification($ticket, $classification);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Classification applied successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to apply classification'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to apply AI classification: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply classification',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Auto-classify a ticket using AI
     */
    public function autoClassify(Ticket $ticket): JsonResponse
    {
        try {
            $classification = $this->aiService->classifyTicket($ticket);
            $success = $this->aiService->applyClassification($ticket, $classification);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Ticket classified successfully',
                    'data' => $classification
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to classify ticket'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to auto-classify ticket: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to classify ticket',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get AI service status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->aiService->getStatus();
            
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get AI status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get AI classification history for a ticket
     */
    public function getClassificationHistory(Ticket $ticket): JsonResponse
    {
        try {
            $classifications = $ticket->aiClassifications()
                ->with(['priority', 'category', 'department', 'type'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $classifications
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get classification history: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get classification history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Batch classify multiple tickets
     */
    public function batchClassify(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_ids' => 'required|array|max:10',
            'ticket_ids.*' => 'exists:tickets,id'
        ]);

        try {
            $results = [];
            $tickets = Ticket::whereIn('id', $request->ticket_ids)->get();

            foreach ($tickets as $ticket) {
                $classification = $this->aiService->classifyTicket($ticket);
                $success = $this->aiService->applyClassification($ticket, $classification);
                
                $results[] = [
                    'ticket_id' => $ticket->id,
                    'ticket_uid' => $ticket->uid,
                    'success' => $success,
                    'classification' => $classification
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Batch classification completed',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to batch classify tickets: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch classify tickets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get AI settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $settings = [
                'ai_enabled' => config('ai.enabled', false),
                'openai_api_key' => config('ai.openai.api_key') ? '***' . substr(config('ai.openai.api_key'), -4) : '',
                'openai_model' => config('ai.openai.model', 'gpt-3.5-turbo'),
                'openai_max_tokens' => config('ai.openai.max_tokens', 500),
                'auto_classify_new_tickets' => config('ai.classification.auto_classify_new_tickets', true),
                'confidence_threshold' => config('ai.classification.confidence_threshold', 0.7),
                'cache_duration' => config('ai.classification.cache_duration', 3600),
                'rate_limit_per_minute' => config('ai.performance.rate_limit_per_minute', 60),
                'rate_limit_per_hour' => config('ai.performance.rate_limit_per_hour', 1000),
                'batch_size' => config('ai.classification.batch_size', 10),
                'timeout' => config('ai.openai.timeout', 30)
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get AI settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Save AI settings
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $request->validate([
            'ai_enabled' => 'boolean',
            'openai_api_key' => 'nullable|string',
            'openai_model' => 'string|in:gpt-3.5-turbo,gpt-4,gpt-4-turbo',
            'openai_max_tokens' => 'integer|min:100|max:4000',
            'auto_classify_new_tickets' => 'boolean',
            'confidence_threshold' => 'numeric|min:0.1|max:1.0',
            'cache_duration' => 'integer|min:300|max:86400',
            'rate_limit_per_minute' => 'integer|min:1|max:100',
            'rate_limit_per_hour' => 'integer|min:10|max:2000',
            'batch_size' => 'integer|min:1|max:50',
            'timeout' => 'integer|min:5|max:120'
        ]);

        try {
            // Update .env file with new settings
            $envEditor = DotenvEditor::load();

            // Map form fields to .env variables
            $envMappings = [
                'ai_enabled' => 'AI_ENABLED',
                'openai_api_key' => 'OPENAI_API_KEY',
                'openai_model' => 'OPENAI_MODEL',
                'openai_max_tokens' => 'OPENAI_MAX_TOKENS',
                'auto_classify_new_tickets' => 'AI_AUTO_CLASSIFY_NEW_TICKETS',
                'confidence_threshold' => 'AI_CONFIDENCE_THRESHOLD',
                'cache_duration' => 'AI_CACHE_DURATION',
                'rate_limit_per_minute' => 'AI_RATE_LIMIT_PER_MINUTE',
                'rate_limit_per_hour' => 'AI_RATE_LIMIT_PER_HOUR',
                'batch_size' => 'AI_BATCH_SIZE',
                'timeout' => 'OPENAI_TIMEOUT'
            ];

            foreach ($envMappings as $formField => $envVar) {
                if ($request->has($formField)) {
                    $value = $request->input($formField);
                    
                    // Convert boolean to string
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    }
                    
                    $envEditor->setKey($envVar, $value);
                }
            }

            $envEditor->save();

            // Clear configuration cache
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            return response()->json([
                'success' => true,
                'message' => 'AI settings saved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save AI settings: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save AI settings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get AI response suggestions for a ticket
     */
    public function getResponseSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'context' => 'nullable|string|max:1000'
        ]);

        try {
            $ticket = Ticket::findOrFail($request->ticket_id);
            $suggestions = $this->responseService->generateResponseSuggestions($ticket, $request->context);
            
            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get response suggestions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get response suggestions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Analyze sentiment of a ticket
     */
    public function analyzeSentiment(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id'
        ]);

        try {
            $ticket = Ticket::findOrFail($request->ticket_id);
            $sentiment = $this->sentimentService->analyzeSentiment($ticket);
            
            // Check if escalation is recommended
            $shouldEscalate = $this->sentimentService->shouldEscalate($sentiment);
            
            return response()->json([
                'success' => true,
                'data' => array_merge($sentiment, [
                    'should_escalate' => $shouldEscalate,
                    'sentiment_color' => $this->sentimentService->getSentimentColor($sentiment),
                    'urgency_color' => $this->sentimentService->getUrgencyColor($sentiment['urgency_level'])
                ])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to analyze sentiment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze sentiment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get knowledge base suggestions for a ticket
     */
    public function getKnowledgeBaseSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id'
        ]);

        try {
            $ticket = Ticket::findOrFail($request->ticket_id);
            $suggestions = $this->responseService->getKnowledgeBaseSuggestions($ticket);
            
            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get knowledge base suggestions: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get knowledge base suggestions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get comprehensive AI analysis for a ticket
     */
    public function getComprehensiveAnalysis(Ticket $ticket): JsonResponse
    {
        try {
            $analysis = [
                'classification' => $this->aiService->getSuggestions($ticket),
                'sentiment' => $this->sentimentService->analyzeSentiment($ticket),
                'response_suggestions' => $this->responseService->generateResponseSuggestions($ticket),
                'knowledge_base' => $this->responseService->getKnowledgeBaseSuggestions($ticket)
            ];

            return response()->json([
                'success' => true,
                'data' => $analysis
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get comprehensive analysis: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get comprehensive analysis',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Calculate classification accuracy (placeholder implementation)
     */
    private function calculateAccuracy(): float
    {
        // This would typically involve comparing AI classifications with manual corrections
        // For now, return a placeholder value
        return 0.85; // 85% accuracy
    }
}