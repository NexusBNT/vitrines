<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'site_id', 'user_id', 'task', 'provider', 'model', 'prompt_version', 'status',
    'input', 'output', 'error', 'input_tokens', 'output_tokens', 'duration_ms',
])]
class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'input' => 'array',
            'output' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
