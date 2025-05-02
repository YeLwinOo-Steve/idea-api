<?php

namespace App\Repositories;

use App\Models\Idea;
use App\Models\User;
use App\Repositories\BasicFunctions\BasicFunctions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportRepository extends BasicFunctions
{

    protected $IdeaModel;
    protected $UserModel;


    public function __construct()
    {
        $this->IdeaModel = new Idea();
        $this->UserModel = new User();
    }

    public function findIdea($id)
    {

        return $this->IdeaModel->find($id);
    }

    public function findUser($id)
    {

        return $this->UserModel->find($id);
    }

    public function hideIdea($id, $hide)
    {

        try {

            DB::beginTransaction();

            $idea = $this->findIdea($id);


            $idea->update(["hidden" => $hide]);

            $this->addLog([
                "user_id" => Auth::id(),
                "type" => "Report",
                "action" => "Hide",
                "activity" => ($hide ? "Hide" : "Un Hide") . " the idea id $id",
            ]);

            DB::commit();


            return "Successfully " . ($hide ? "hide" : "un hide") . " the idea id $id";


        } catch (\Throwable $e) {
            DB::rollBack();

            return $e;
        }
    }

    public function hideAllUserPosts($id, $hide)
    {

        try {

            DB::beginTransaction();

            $user = $this->findUser($id);


            $user->ideas()->update(['hidden' => $hide]);
            $user->comments()->update(['hidden' => $hide]);

            $user->update(['hidden' => $hide]);


            $this->addLog([
                "user_id" => Auth::id(),
                "type" => "report",
                "action" => "hide",
                "activity" => ($hide ? "Hide" : "Un Hide") . " the user id $id's idea and comments",

            ]);

            DB::commit();

            return "Successfully ". ($hide ? "Hide" : "Un Hide") . " the user id $id's idea and comments";

        } catch (\Throwable $e) {
            DB::rollBack();

            return $e;
        }
    }



    public function banAndAccess($id, $ban)
    {

        try {

            DB::beginTransaction();

            $user = $this->findUser($id);

            if ($ban) {

                $user->permissions()->detach([11, 12]);

            } else {

                $targetPermissions = [11, 12];

                $existingPermissions = $user->permissions()->pluck('permission_id')->toArray();

                $permissionsToAttach = array_diff($targetPermissions, $existingPermissions);

                if (!empty($permissionsToAttach)) {
                    $user->permissions()->attach($permissionsToAttach);
                }

            }

            $this->addLog([
                "user_id" => Auth::id(),
                "type" => "report",
                "action" => "ban",
                "activity" => ($ban ? "Ban" : "Regive") . " post idea and comment permissions to the user id $id",
            ]);

            DB::commit();


            return  "successfully " . ($ban ? "ban" : "access") . " post idea and comment permissions to the user id $id";

        } catch (\Throwable $e) {
            DB::rollBack();

            return $e;
        }
    }
}
