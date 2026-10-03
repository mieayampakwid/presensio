<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationDeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = NotificationDelivery::query()->latest();

        if ($request->filled('type')) {
            $query->where('type_key', $request->string('type'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->string('date'));
        }

        $deliveries = $query->paginate(20)->withQueryString()->through(fn ($d) => [
            'id' => $d->id,
            'dedupe_key' => $d->dedupe_key,
            'type_key' => $d->type_key,
            'channel' => $d->channel,
            'recipient_type' => $d->recipient_type,
            'recipient_id' => $d->recipient_id,
            'recipient_contact' => $d->recipient_contact,
            'status' => $d->status instanceof DeliveryStatus ? $d->status->value : (string) $d->status,
            'scheduled_for' => $d->scheduled_for?->toISOString(),
            'attempts' => $d->attempts,
            'provider_message_id' => $d->provider_message_id,
            'error_message' => $d->error_message,
            'sent_at' => $d->sent_at?->toISOString(),
            'created_at' => $d->created_at?->diffForHumans(),
        ]);

        $types = collect(NotificationType::cases())->map(fn (NotificationType $t) => [
            'value' => $t->value,
            'label' => $t->value,
        ])->all();

        $statuses = array_map(fn (DeliveryStatus $s) => $s->value, DeliveryStatus::cases());

        return Inertia::render('admin/notifications/deliveries', [
            'deliveries' => $deliveries,
            'filters' => [
                'type' => $request->string('type')->toString() ?: null,
                'channel' => $request->string('channel')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'date' => $request->string('date')->toString() ?: null,
            ],
            'types' => $types,
            'channels' => ['whatsapp', 'email'],
            'statuses' => $statuses,
        ]);
    }
}
