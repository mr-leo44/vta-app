<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\AircraftsImport;
use App\Imports\AircraftTypesImport;
use App\Imports\OperatorsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

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

        $delimiter = $request->input('delimiter', ';');
        $encoding  = $request->input('encoding', 'UTF-8');

        // Instancier la bonne classe d'import avec les options CSV
        $import = match ($request->input('type')) {
            'operators'      => new OperatorsImport($delimiter, $encoding),
            'aircrafts'      => new AircraftsImport($delimiter, $encoding),
            'aircraft-types' => new AircraftTypesImport($delimiter, $encoding),
        };

        // Laravel Excel détecte automatiquement le format (xlsx/xls/csv)
        // via l'extension du fichier uploadé.
        Excel::import($import, $request->file('file'));

        return response()->json([
            'created' => $import->created,
            'updated' => $import->updated,
            'failed'  => count($import->errors),
            'errors'  => $import->errors,
        ]);
    }
}