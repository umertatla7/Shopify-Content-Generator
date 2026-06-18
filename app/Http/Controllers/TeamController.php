<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $account = Account::query()
            ->with(['users' => fn ($query) => $query->select('users.id', 'name', 'email')])
            ->findOrFail($request->user()->current_account_id);

        $this->authorize('invite', $account);

        return Inertia::render('Team/Index', [
            'account' => $account,
            'members' => $account->users,
            'invitationPlaceholder' => true,
        ]);
    }
}
