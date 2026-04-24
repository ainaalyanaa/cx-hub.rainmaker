<?php

namespace Database\Factories;

use App\Models\AITicketClassification;
use App\Models\Ticket;
use App\Models\Priority;
use App\Models\Category;
use App\Models\Department;
use App\Models\Type;
use Illuminate\Database\Eloquent\Factories\Factory;

class AITicketClassificationFactory extends Factory
{
    protected $model = AITicketClassification::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::inRandomOrder()->first()?->id ?? Ticket::factory(),
            'priority_id' => Priority::inRandomOrder()->first()?->id,
            'category_id' => Category::inRandomOrder()->first()?->id,
            'department_id' => Department::inRandomOrder()->first()?->id,
            'type_id' => Type::inRandomOrder()->first()?->id,
            'confidence_score' => $this->faker->randomFloat(2, 0.5, 1),
            'ai_generated' => true,
            'applied' => true,
            'reasoning' => $this->faker->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
