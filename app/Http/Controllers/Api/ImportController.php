<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessExcelImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    /**
     * POST /api/imports
     *
     * Champs multipart/form-data :
     * ┌─────────────┬──────────┬─────────────────────────────────────────────┐
     * │ Champ       │ Requis   │ Valeurs                                     │
     * ├─────────────┼──────────┼─────────────────────────────────────────────┤
     * │ file        │ oui      │ .xlsx, .xls, .csv                           │
     * │ type        │ oui      │ operators | aircrafts | aircraft-types       │
     * │ delimiter   │ non      │ ; , | \t  (CSV uniquement, défaut: ;)        │
     * │ encoding    │ non      │ UTF-8 | ISO-8859-1 | Windows-1252           │
     * │ has_header  │ non      │ 1 | 0  (défaut: 1)                          │
     * └─────────────┴──────────┴─────────────────────────────────────────────┘
     *
     * Réponse JSON :
     * {
     *   "created": int,
     *   "updated": int,
     *   "failed":  int,
     *   "errors":  [{ "row": int, "message": string }, ...]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'type'       => 'required|in:operators,aircrafts,aircraft-types',
            'delimiter'  => 'nullable|string|max:5',
            'encoding'   => 'nullable|string|max:20',
            'has_header' => 'nullable|in:0,1',
        ]);

        // Sauvegarder le fichier temporaire
        $filePath = $request->file('file')->store('imports', 'local');
        $fullPath = storage_path("app/{$filePath}");

        // Dispatcher le job de traitement avec l'ID de l'utilisateur connecté
        ProcessExcelImport::dispatch(
            importType: $request->input('type'),
            filePath:   $fullPath,
            userId:     $request->user()->id,
            delimiter:  $request->input('delimiter', ';'),
            encoding:   $request->input('encoding', 'UTF-8')
        );

        return response()->json([
            'message' => 'Import lancé en arrière-plan. Vérifiez plus tard.',
            'file'    => $filePath,
        ], 202);
    }
}