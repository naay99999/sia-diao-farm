<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ResetMemberPasswordRequest;
use App\Http\Requests\Settings\StoreMemberRequest;
use App\Http\Requests\Settings\UpdateMemberRequest;
use App\Models\User;
use App\Support\AiConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * List every account, the owner first, with whether each has Claude connected.
     */
    public function index(): Response
    {
        $members = User::query()
            ->withExists('passkeys')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'isOwner' => $user->is_owner,
                'isActive' => $user->isActive(),
                'hasPasskey' => $user->passkeys_exists,
                'isConnected' => AiConnections::forUser($user->id) !== [],
            ]);

        return Inertia::render('settings/members', [
            'members' => $members,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create a family member's account, ready to log in.
     */
    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->email_verified_at = now();
        $user->save();

        return $this->done('เพิ่มสมาชิกแล้ว');
    }

    /**
     * Change a member's name and email.
     */
    public function update(UpdateMemberRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return $this->done('บันทึกแล้ว');
    }

    /**
     * Set a new password for a member who forgot theirs, or whose account may be in the wrong hands. Their
     * other sessions end on their next request (auth.session); their Claude connections are cut off now.
     */
    public function resetPassword(ResetMemberPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        AiConnections::revokeAll($user->id);

        return $this->done('ตั้งรหัสผ่านใหม่แล้ว');
    }

    /**
     * Lock a member out and cut off the Claude connections made with their account.
     */
    public function deactivate(User $user): RedirectResponse
    {
        abort_if($user->is_owner, 403);

        $user->deactivate();

        return $this->done('ปิดใช้งานแล้ว');
    }

    /**
     * Let a deactivated member log in again.
     */
    public function activate(User $user): RedirectResponse
    {
        $user->activate();

        return $this->done('เปิดใช้งานแล้ว');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('members.index');
    }
}
