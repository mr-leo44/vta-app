<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AuditController — lecture seule, admin uniquement.
 *
 * GET /api/audit            → liste paginée + filtres
 * GET /api/audit/stats      → métriques pour le dashboard audit
 * GET /api/audit/actors     → liste des acteurs (pour le filtre du front)
 */
class AuditController extends Controller
{
    private const ALLOWED_EVENTS = [
        'created', 'updated', 'deleted', 'restored',
        'function_assigned', 'permission_granted', 'permission_revoked',
    ];

    private const ALLOWED_MODELS = [
        'Flight', 'Aircraft', 'AircraftType', 'Operator', 'User',
    ];

    /**
     * GET /api/audit
     *
     * Filtres disponibles :
     *  ?event=updated
     *  ?model=Flight
     *  ?actor_id=3
     *  ?from=2026-01-01&to=2026-03-31
     *  ?search=  (recherche dans old_values / new_values en JSON)
     *  ?per_page=25
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('user.viewAny'); // réservé admin

        $request->validate([
            'event'    => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_EVENTS)],
            'model'    => ['nullable', 'string', 'in:' . implode(',', self::ALLOWED_MODELS)],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'from'     => ['nullable', 'date_format:Y-m-d'],
            'to'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::with('actor:id,name,username')
            ->recent(); // orderBy created_at desc

        if ($event = $request->input('event')) {
            $query->byEvent($event);
        }

        if ($model = $request->input('model')) {
            $query->forModel('App\\Models\\' . $model);
        }

        if ($actorId = $request->integer('actor_id')) {
            $query->byActor($actorId);
        }

        if ($from = $request->input('from')) {
            $to = $request->input('to', now()->toDateString());
            $query->between($from, $to);
        }

        $logs = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $logs->getCollection()->map(fn ($log) => [
                'id'             => $log->id,
                'event'          => $log->event,
                'event_label'    => $log->eventLabel(),
                'model'          => $log->auditableLabel(),
                'model_id'       => $log->auditable_id,
                'model_name'     => User::find($log->auditable_id, 'name'),
                'actor'          => $log->actor ? [
                    'id'       => $log->actor->id,
                    'name'     => $log->actor->name,
                    'username' => $log->actor->username,
                ] : null,
                'actor_ip'       => $log->actor_ip,
                'old_values'     => $log->old_values,
                'new_values'     => $log->new_values,
                'created_at'     => $log->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * GET /api/audit/stats
     * Métriques pour le widget dashboard de la page audit.
     */
    public function stats(): JsonResponse
    {
        $this->authorize('user.viewAny');

        $today     = now()->toDateString();
        $thisWeek  = now()->startOfWeek()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        return response()->json([
            'total'       => AuditLog::count(),
            'today'       => AuditLog::between($today, $today)->count(),
            'this_week'   => AuditLog::between($thisWeek, $today)->count(),
            'this_month'  => AuditLog::between($thisMonth, $today)->count(),
            'by_event'    => AuditLog::selectRaw('event, count(*) as total')
                                ->groupBy('event')
                                ->pluck('total', 'event'),
            'by_model'    => AuditLog::selectRaw('auditable_type, count(*) as total')
                                ->groupBy('auditable_type')
                                ->get()
                                ->mapWithKeys(fn ($r) => [
                                    class_basename($r->auditable_type) => $r->total,
                                ]),
            'top_actors'  => AuditLog::selectRaw('actor_id, count(*) as total')
                                ->whereNotNull('actor_id')
                                ->groupBy('actor_id')
                                ->orderByDesc('total')
                                ->limit(5)
                                ->with('actor:id,name,username')
                                ->get()
                                ->map(fn ($r) => [
                                    'actor' => $r->actor?->name,
                                    'total' => $r->total,
                                ]),
        ]);
    }

    /**
     * GET /api/audit/actors
     * Liste des acteurs ayant au moins un log (pour le filtre du front).
     */
    public function actors(): JsonResponse
    {
        $this->authorize('user.viewAny');

        $actors = User::whereIn('id',
            AuditLog::whereNotNull('actor_id')->distinct()->pluck('actor_id')
        )->select('id', 'name', 'username')->get();

        return response()->json(['data' => $actors]);
    }
}
