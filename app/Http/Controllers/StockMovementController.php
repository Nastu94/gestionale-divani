<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(StockMovement $stockMovement)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockMovement $stockMovement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockMovement $stockMovement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockMovement $stockMovement)
    {
        //
    }
    
    // Elenco Entrate
    public function indexEntry()
    {
        // Carichi e passi le entrate…
    }

    // Store Entrata
    public function storeEntry(Request $request)
    {
        // Logica di validazione e creazione…
    }

    // Elenco Uscite
    public function indexExit()
    {
        // Carichi e passi le uscite…
    }

    // Update Uscita
    public function updateExit(Request $request, StockMovement $stock_movement)
    {
        // Logica di validazione e update…
    }
}
