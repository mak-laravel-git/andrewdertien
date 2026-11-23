<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Toss James a Ball" on the public website. Signed-in members go straight to that person's
 * profile; visitors sign up first and are then sent back to the person they picked.
 */
class TossController extends Controller
{
    public const SESSION_KEY = 'toss_to';

    public function __invoke(Request $request, string $username): RedirectResponse
    {
        $member = User::query()->discoverable()->where('username', $username)->first();

        if ($request->user()) {
            return $member
                ? redirect()->route('members.show', ['user' => $member->username, 'toss' => 1])
                : redirect()->route('members.index');
        }

        if ($member) {
            $request->session()->put(self::SESSION_KEY, $member->username);
        }

        return redirect()->route('register');
    }

    /**
     * The username of the person this visitor came to toss a ball to, if they picked one. It is used once.
     */
    public static function pullChosenMember(Request $request): ?string
    {
        $username = $request->session()->pull(self::SESSION_KEY);

        return is_string($username) && User::query()->discoverable()->where('username', $username)->exists()
            ? $username
            : null;
    }
}
