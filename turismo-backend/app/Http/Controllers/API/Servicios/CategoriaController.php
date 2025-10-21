<?php

namespace App\Http\Controllers\API\Servicios;

use App\Http\Controllers\Controller;
use App\Repository\CategoriaRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriaController extends Controller
{
    protected $repository;

    public function __construct(CategoriaRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(): JsonResponse
    {
        $categorias = $this->repository->getAll();
        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $categoria = $this->repository->findWithServicios($id);
        
        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $categoria
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'icono_url' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $categoria = $this->repository->create($request->all());
        
        return response()->json([
            'success' => true,
            'data' => $categoria,
            'message' => 'Categoría creada exitosamente'
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'icono_url' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = $this->repository->update($id, $request->all());
        
        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $this->repository->findById($id),
            'message' => 'Categoría actualizada exitosamente'
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->repository->delete($id);
        
        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente'
        ]);
    }
}