<?php
namespace App\Console\Commands;
use App\Models\Attachment;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AITicketClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class PipipingEmail extends Command
{
    protected $signature = 'command:piping_email';
    protected $description = 'Fetch ALL emails from IMAP and convert them into tickets';
    
    protected $aiService;

    public function __construct(AITicketClassificationService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    public function handle()
    {
        $this->info('COMMAND STARTED');
        Log::info('COMMAND STARTED');

        // Check if AI is enabled globally
        $aiEnabled = config('ai.enabled', false);
        $autoClassify = config('ai.classification.auto_classify_new_tickets', true);
        
        if ($aiEnabled && $autoClassify) {
            $this->info('AI auto-classification is ENABLED');
        } else {
            $this->info('AI auto-classification is DISABLED');
        }

        try {
            $client = Client::account('default');
            $client->connect();
        } catch (\Throwable $e) {
            Log::error('IMAP connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            $folder = $client->getFolder('INBOX');
        } catch (\Throwable $e) {
            Log::error('Unable to access INBOX: ' . $e->getMessage());
            return Command::FAILURE;
        }

        try {
            $messages = $folder->messages()
                ->all()
                ->setFetchOrder('asc')
                ->limit(50)
                ->get();

            $this->info('Messages found: ' . $messages->count());
            Log::info('Messages found: ' . $messages->count());

        } catch (\Throwable $e) {
            Log::error('Failed fetching messages: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $newTickets = []; // Store newly created tickets

        foreach ($messages as $message) {
            try {
                $messageId = $message->getMessageId();

                $this->info('Processing message: ' . $messageId);
                Log::info('Processing message: ' . $messageId);

                $from = $message->getFrom();
                if (empty($from)) {
                    Log::warning('Message skipped: no FROM');
                    continue;
                }

                $fromData = $from[0];

                // Create or get user
                $roleId = Role::where('slug', 'customer')->value('id') ?? 5;
                [$firstName, $lastName] = $this->split_name($fromData->personal ?? '');

                $user = User::firstOrCreate(
                    ['email' => $fromData->mail],
                    [
                        'password' => bcrypt('secret'),
                        'role_id' => $roleId,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ]
                );

                // Create ticket
                $ticket = Ticket::create([
                    'subject' => $message->getSubject() ?? '(No Subject)',
                    'details' => $message->getHTMLBody() ?? $message->getTextBody(),
                    'user_id' => $user->id,
                    'open' => now(),
                    'response' => null,
                    'due' => null,
                ]);

                $newTickets[] = $ticket; // Store for AI classification

                // Handle attachments
                $message->getAttachments()->each(function ($attachment) use ($message, $ticket, $user) {
                    try {
                        $originName = $message->getMessageId() . '_' . $attachment->name;
                        $storagePath = public_path('files/tickets/');

                        if (!is_dir($storagePath)) {
                            mkdir($storagePath, 0775, true);
                        }

                        file_put_contents(
                            $storagePath . $originName,
                            $attachment->content
                        );

                        Attachment::create([
                            'ticket_id' => $ticket->id,
                            'name' => $attachment->name,
                            'size' => $attachment->size,
                            'path' => 'tickets/' . $originName,
                            'user_id' => $user->id,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Attachment error: ' . $e->getMessage());
                    }
                });

                // Mark message as seen AFTER success
                $message->setFlag('SEEN');

            } catch (\Throwable $e) {
                Log::error('Message processing error: ' . $e->getMessage());
            }
        }

        // **NEW: AI CLASSIFICATION SECTION**
        $this->classifyNewTickets($newTickets);

        $this->info('COMMAND FINISHED');
        Log::info('COMMAND FINISHED');

        return Command::SUCCESS;
    }

    /**
     * AI Classification for newly created tickets
     */
    private function classifyNewTickets(array $tickets): void
    {
        if (empty($tickets)) {
            $this->info('No new tickets to classify');
            return;
        }

        // Check if AI classification should run
        $aiEnabled = config('ai.enabled', false);
        $autoClassify = config('ai.classification.auto_classify_new_tickets', true);
        
        if (!$aiEnabled || !$autoClassify) {
            $this->info('Skipping AI classification (disabled in settings)');
            return;
        }

        $this->info('Starting AI classification for ' . count($tickets) . ' new tickets');
        Log::info('AI Classification: Processing ' . count($tickets) . ' new tickets');

        $classifiedCount = 0;
        $failedCount = 0;

        foreach ($tickets as $ticket) {
            try {
                $this->info("Classifying Ticket #{$ticket->id}: {$ticket->subject}");
                
                // Get AI classification
                $classification = $this->aiService->classifyTicket($ticket);
                
                // Apply classification
                $success = $this->aiService->applyClassification($ticket, $classification);
                
                if ($success) {
                    $classifiedCount++;
                    $this->info("✓ Successfully classified (Confidence: {$classification['confidence']})");
                    Log::info("AI Classification: Ticket #{$ticket->id} classified successfully");
                } else {
                    $failedCount++;
                    $this->error("✗ Failed to apply classification for Ticket #{$ticket->id}");
                    Log::error("AI Classification: Failed to apply classification for Ticket #{$ticket->id}");
                }
                
                // Small delay to avoid API rate limiting
                $delayMs = config('ai.performance.rate_limit_delay', 500);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000); // Convert ms to microseconds
                }
                
            } catch (\Throwable $e) {
                $failedCount++;
                $errorMsg = "AI Classification failed for Ticket #{$ticket->id}: " . $e->getMessage();
                Log::error($errorMsg);
                $this->error($errorMsg);
            }
        }

        // Summary
        $this->info("\n" . str_repeat('=', 50));
        $this->info("AI CLASSIFICATION SUMMARY");
        $this->info(str_repeat('=', 50));
        $this->info("Total tickets: " . count($tickets));
        $this->info("Successfully classified: " . $classifiedCount);
        $this->info("Failed: " . $failedCount);
        $this->info(str_repeat('=', 50));
        
        Log::info("AI Classification Summary: {$classifiedCount}/" . count($tickets) . " tickets classified successfully");
    }

    private function split_name(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', ''];
        }

        $lastName = (strpos($name, ' ') === false)
            ? ''
            : preg_replace('#.*\s([\w-]*)$#', '$1', $name);

        $firstName = trim(
            preg_replace('#' . preg_quote($lastName, '#') . '#', '', $name)
        );

        return [$firstName, $lastName];
    }
}