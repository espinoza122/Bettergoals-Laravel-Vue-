<?php

namespace Laravel\Spark\Interactions\Settings\Teams;

use Ramsey\Uuid\Uuid;
use Laravel\Spark\Spark;
use Laravel\Spark\Invitation;
use Illuminate\Support\Facades\Mail;
use Laravel\Spark\Events\Teams\UserInvitedToTeam;
use Laravel\Spark\Contracts\Interactions\Settings\Teams\SendInvitation as Contract;
use Illuminate\Support\Facades\DB;
class SendInvitation implements Contract
{
    /**
     * {@inheritdoc}
     */
    public function handle($team, $email)
    {
        $invitedUser = Spark::user()->where('username', $email)->first();
        $invitation = null;
        $this->emailInvitation(
            $invitation = $this->createInvitation($team, $email, $invitedUser)
        );
        if($invitedUser){
            DB::table('team_users')->insert(
                ['team_id' => $team->id, 'user_id' => $invitedUser->id , 'role' => 'member']
            );
        }

        if ($invitedUser){
            event(new UserInvitedToTeam($team, $invitedUser));
        }

        return $invitation;
    }

    /**
     * E-mail the given invitation instance.
     *
     * @param  Invitation  $invitation
     * @return void
     */
    protected function emailInvitation($invitation)
    {
        Mail::send($this->view($invitation), compact('invitation'), function ($m) use ($invitation) {
            $m->to($invitation->email)->subject('New Team Invitation on Better Goals');
        });
        if($invitation->user_id){
            $invitation->delete();
        }
    }

    /**
     * Create a new invitation instance.
     *
     * @param  \Laravel\Spark\Team  $team
     * @param  string  $email
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $invitedUser
     * @return Invitation
     */
    protected function createInvitation($team, $email, $invitedUser)
    {
        return $team->invitations()->create([
            'id' => Uuid::uuid4(),
            'user_id' => $invitedUser ? $invitedUser->id : null,
            'email' => $invitedUser ? $invitedUser->email : $email,
            'token' => str_random(40),
        ]);
    }

    /**
     * Get the proper e-mail view for the given invitation.
     *
     * @param  \Laravel\Spark\Invitation  $invitation
     * @return string
     */
    protected function view(Invitation $invitation)
    {
        return 'spark::settings.teams.emails.invitation-to-new-user';
        return $invitation->user_id
                        ? 'spark::settings.teams.emails.invitation-to-existing-user'
                        : 'spark::settings.teams.emails.invitation-to-new-user';
    }
}
