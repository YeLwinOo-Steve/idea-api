<?php

namespace App\Repositories;

use App\Models\Idea;
use App\Repositories\BasicFunctions\BasicFunctions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IdeaRepository extends BasicFunctions
{
    protected $model;

    public function __construct()
    {
        $this->model = new Idea();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function create(array $data)
    {

        try {

            DB::beginTransaction();
            $categories = explode(',', $data["category"]);
            $idea = $this->model->create($data);
            $idea->categories()->attach($categories);

            if( isset($data["document"])){

                $documents = json_decode($data["document"]);
                foreach ($documents as $document) {
                    $idea->files()->create([
                        "file_name" => $document->file_name,
                        "file_path" => $document->file_path,
                    ]);
                }
            }

            DB::commit();
            return $idea;

        } catch (\Throwable $e) {
            DB::rollBack();

            return $e;
        }

    }

    public function update($id, array $data)
    {
        try {

            DB::beginTransaction();

            $idea = $this->find($id);

            $oldCategoriesID = $idea->categories->pluck('id')->toArray();
            $newCategoriesID = explode(',', $data["category"]);


            $categoriesToDelete = array_diff($oldCategoriesID,$newCategoriesID);
            $categoriesToAdd = array_diff($newCategoriesID, $oldCategoriesID);

            if ($categoriesToAdd) {
                $idea->categories()->attach($categoriesToAdd);
            }
            if ($categoriesToDelete) {
                $idea->categories()->detach($categoriesToDelete);
            }


            $idea->files()->delete();

            if( isset($data["document"])){

                $documents = json_decode($data["document"]);

                foreach ($documents as $document) {
                    $idea->files()->create([
                        "file_name" => $document->file_name,
                        "file_path" => $document->file_path,
                    ]);
                }

            }

            $idea->update($data);

            DB::commit();

            return $idea;

        } catch (\Throwable $e) {

            DB::rollBack();

            return $e;
        }
    }

    public function updateIdeaCategory($id,$data){
        try {

            DB::beginTransaction();

            $idea = $this->find($id);

            $oldCategoriesID = $idea->categories->pluck('id')->toArray();
            $newCategoriesID = explode(',', $data["category"]);


            $categoriesToDelete = array_diff($oldCategoriesID,$newCategoriesID);
            $categoriesToAdd = array_diff($newCategoriesID, $oldCategoriesID);

            if ($categoriesToAdd) {
                $idea->categories()->attach($categoriesToAdd);
            }
            if ($categoriesToDelete) {
                $idea->categories()->detach($categoriesToDelete);
            }

            $this->addLog([
               "user_id" => Auth::id(),
                "type" => "idea",
                "action" => "update",
                "activity" => "Update the Idea Category" ,
            ]);

            DB::commit();

            return $idea;

        } catch (\Throwable $e) {

            DB::rollBack();

            return $e;
        }

    }

    public function submitIdea($id,$data){

        try {

            DB::beginTransaction();

            $idea = $this->find($id);

            $this->addLog([
               "user_id" => Auth::id(),
                "type" => "idea",
                "action" => $data["is_enabled"]? "submit":"un_submit",
                "activity" => $data["is_enabled"]? "submit":"un_submit" . " idea ".$idea->id,
            ]);

            $idea->update($data);

            DB::commit();

            return $idea;

        }catch (\Throwable $e) {

            DB::rollBack();

            return $e;
        }

    }

    public function destroy($id)
    {
        try {

            DB::beginTransaction();

            $idea = $this->find($id);

            $this->addLog([
               "user_id" => Auth::id(),
                "type" => "idea",
                "action" => "delete",
                "activity" => "Delete the Idea ".$idea->id,
            ]);

            $ideaCategories = $idea->categories->pluck('id')->toArray();

            $idea->categories()->detach($ideaCategories);

            $idea->comment()->delete();

            $idea->votes()->delete();

            $idea->files()->delete();

            $idea->reports()->delete();

            $idea->delete();

            DB::commit();

            return true;

        }catch (\Throwable $e) {

            DB::rollBack();

            return $e;
        }
    }
}
