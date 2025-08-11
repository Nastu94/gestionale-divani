<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Lista utenti con filtro/ordinamento per Nome e Ruoli.
     *
     * - sort:  'name' | 'roles'                  (default: 'name')
     * - dir:   'asc'  | 'desc'                   (default: 'asc')
     * - filter[name]:  filtro LIKE su users.name
     * - filter[roles]: filtro LIKE su roles.name (via whereHas)
     *
     * Nota: per l'ordinamento su "roles" usiamo una subquery con GROUP_CONCAT
     *       (MySQL). Se usi Postgres, sostituisci con string_agg().
     */
    public function index(Request $request)
    {
        /* ─────────────────────────────────────────────
         | Parametri query (coerenti con gli altri index)
         ───────────────────────────────────────────── */
        $sort    = $request->input('sort', 'name');                // campo sort
        $dir     = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $filters = $request->input('filter', []);                  // array filtri

        /* Whitelist dei campi ordinabili */
        $allowedSorts = ['name', 'roles'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        /* ─────────────────────────────────────────────
         | Query base
         ───────────────────────────────────────────── */
        $users = User::query()
            ->with('roles') // eager-load ruoli per stampa tabella
            /* ---- filtri colonna -------------------------------------- */
            ->when($filters['name'] ?? null,
                fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($filters['roles'] ?? null,
                fn ($q, $v) => $q->whereHas('roles',
                    fn ($qr) => $qr->where('name', 'like', "%{$v}%")))
            /* ---- ordinamento ----------------------------------------- */
            ->when($sort === 'roles', function ($q) use ($dir) {
                // MySQL: ordiniamo per i ruoli concatenati dell'utente
                // (Admin,Impiegato,...) per consistenza alfabetica.
                $orderExpr = "(SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ',')
                               FROM model_has_roles mr
                               JOIN roles r ON r.id = mr.role_id
                              WHERE mr.model_type = ?
                                AND mr.model_id   = users.id)";
                $q->orderByRaw($orderExpr . ' ' . $dir, [User::class]);
            }, function ($q) use ($sort, $dir) {
                $q->orderBy($sort, $dir);
            })
            ->paginate(15)
            ->appends($request->query()); // preserva sort+filtri in pagina

        /* Dati per il modale Crea/Modifica utente (select ruoli) */
        $roles = Role::orderBy('name')->get(['id', 'name']);

        /* Ritorno vista con variabili standardizzate */
        return view('pages.users.index', compact('users', 'roles', 'sort', 'dir', 'filters'));
    }

    /**
     * Mostra il form per creare un nuovo utente.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('pages.users.create');
    }

    /**
     * Salva un nuovo utente nel database e assegna il ruolo selezionato.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {

        //Validazione con try-catch per intercettare errori
        try {
            $data = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'role'     => 'required|string|exists:roles,name',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log errori di validazione
            Log::error('UserController@store - validazione fallita', $e->validator->errors()->toArray());
            return redirect()->back()
                             ->withErrors($e->validator)
                             ->withInput();
        }

        try {
            // Creazione utente
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            // Assegna ruolo usando Spatie Permission
            $user->assignRole($data['role']);

        } catch (\Exception $e) {
            // Log completo dell'eccezione
            Log::error('UserController@store - eccezione creazione o assegnazione', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('users.index')
                ->withErrors('Errore durante creazione utente.');
        }

        // Redirect con successo
        return redirect()
            ->route('users.index')
            ->with('success', 'Utente creato con ruolo.');
    }

    /**
     * Mostra i dettagli di un singolo utente.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\View\View
     */
    public function show(User $user)
    {
        return view('pages.users.show', compact('user'));
    }

    /**
     * Mostra il form per modificare un utente esistente.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\View\View
     */
    public function edit(User $user)
    {
        return view('pages.users.edit', compact('user'));
    }

    /**
     * Aggiorna i dati di un utente esistente e sincronizza il ruolo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        // Validazione
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required','email', Rule::unique('users','email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role'     => 'required|string|exists:roles,name',
        ]);

        // Aggiorno i campi base
        $user->name = $data['name'];
        $user->email = $data['email'];

        // Se la password è stata fornita, la criptiamo
        if (!empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();

        // Sincronizzo il ruolo selezionato
        $user->syncRoles([$data['role']]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Utente aggiornato correttamente');
    }

    /**
     * Elimina un utente dal database.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'Utente eliminato');
    }
}
