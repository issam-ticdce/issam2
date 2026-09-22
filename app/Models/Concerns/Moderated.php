<?php

namespace App\Models\Concerns;

use App\Models\Startup;
use App\Notifications\ContentReviewed;
use App\Notifications\ContentSubmitted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Circuit de validation par le TICDCE.
 *
 * - Tant qu'un contenu n'a jamais été publié, la startup modifie directement les champs.
 * - Une fois publié, ses modifications sont stockées dans "draft" : la version publique
 *   reste inchangée jusqu'à ce que le TICDCE approuve.
 */
trait Moderated
{
    /** Champs que la startup peut modifier (soumis à validation). */
    abstract public static function moderatedFields(): array;

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('review_status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->review_status === 'pending';
    }

    /** Clé de statut : published | pending | pending_changes | rejected | draft. */
    public function statusKey(): string
    {
        return match (true) {
            $this->review_status === 'pending' => $this->is_published ? 'pending_changes' : 'pending',
            $this->review_status === 'rejected' => 'rejected',
            $this->is_published => 'published',
            default => 'draft',
        };
    }

    public static function statusColor(string $key): string
    {
        return match ($key) {
            'published' => 'success',
            'pending', 'pending_changes' => 'warning',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /** Données à afficher dans le formulaire de la startup (version en attente si elle existe). */
    public function editableState(): array
    {
        return array_merge(
            Arr::only($this->attributesToArray(), static::moderatedFields()),
            $this->draft ?? [],
        );
    }

    /** Enregistre les modifications de la startup et les envoie en validation. */
    public function submitForReview(array $data): void
    {
        $data = Arr::only($data, static::moderatedFields());

        if ($this->is_published) {
            $this->draft = $data;
        } else {
            $this->fill($data);
            $this->draft = null;
        }

        $this->review_status = 'pending';
        $this->rejection_reason = null;
        $this->submitted_at = now();
        $this->save();

        static::safely(fn () => Notification::route('mail', config('ticdce.admin_email'))
            ->notify((new ContentSubmitted($this))->locale('fr')));
    }

    public function approve(): void
    {
        if ($this->draft) {
            $this->fill($this->draft);
        }

        $this->draft = null;
        $this->review_status = 'none';
        $this->rejection_reason = null;
        $this->is_published = true;
        $this->approved_at = now();
        $this->save();

        $this->notifyStartupUsers(new ContentReviewed($this, approved: true));
    }

    public function reject(string $reason): void
    {
        $this->review_status = 'rejected';
        $this->rejection_reason = $reason;
        $this->save();

        $this->notifyStartupUsers(new ContentReviewed($this, approved: false));
    }

    /** Copie non enregistrée avec les modifications en attente appliquées (pour l'aperçu). */
    public function withDraftApplied(): static
    {
        $copy = clone $this;

        if ($this->draft) {
            $copy->fill($this->draft);
        }

        return $copy;
    }

    protected function notifyStartupUsers($notification): void
    {
        $startup = $this instanceof Startup ? $this : $this->startup;

        static::safely(fn () => Notification::send($startup->users, $notification));
    }

    /** Un email qui échoue (serveur SMTP indisponible…) ne doit pas bloquer l'enregistrement. */
    protected static function safely(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error('Envoi email impossible : '.$e->getMessage());
        }
    }
}
