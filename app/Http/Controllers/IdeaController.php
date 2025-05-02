<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIdeaRequest;
use App\Http\Requests\SubmitIdeaRequest;
use App\Http\Requests\UpdateIdeaCategoryRequest;
use App\Http\Requests\UpdateIdeaRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\IdeaResource;
use App\Mail\ApproveMail;
use App\Mail\PostIdeaMail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Idea;
use App\Models\SystemSetting;
use App\Repositories\IdeaRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class IdeaController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    use AuthorizesRequests;
    protected $ideaRepository;

    public function __construct(IdeaRepository $ideaRepository)
    {
        $this->ideaRepository = $ideaRepository;
    }

    protected function checkCategory($categories)
    {

        $categoryArr = explode(',', $categories);

        foreach ($categoryArr as $id) {
            $validated = Validator::make(['id' => $id], [
                'id' => 'required|integer|exists:categories,id',
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'message' => "Category ID $id is Invalid Category ID"
                ], 404);
            }
        }

        return null;
    }

    protected function checkID($id)
    {
        $validated = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:ideas,id',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Invalid idea ID'
            ], 404);
        }

        return null;
    }

    public function index(Request $request)
    {

        $departmentQuery = $request->input("department");
        $popularQuery = $request->input("popular");
        $categoryQuery = $request->input("category");
        $popularQuery = $request->input("popular");
        $latestQuery = $request->input("latest");
        $titleQuery = $request->input("title");


        $ideas = Idea::query();

        if ($titleQuery) {
            $ideas->where('title', "like", "%" . $titleQuery . "%");
        }

        if ($departmentQuery) {

            $ideas->where("is_anonymous", false);

            $ideas->whereHas("user", function ($q) use ($departmentQuery) {
                $q->where('department_id', $departmentQuery);
            });
        }

        if ($popularQuery) {
            $ideas->withSum('votes', 'vote_value')->orderBy('votes_sum_vote_value', $popularQuery);
        }

        if ($categoryQuery) {

            $ideas->whereHas('categories', function ($q) use ($categoryQuery) {
                return $q->where('name', $categoryQuery);
            });
        }

        $ideas->where("is_enabled", true)->where("hidden",false);



        if ($latestQuery) {
            $ideas->orderBy("id", "desc");
        }

        $ideas = $ideas->paginate(6);

        return IdeaResource::collection($ideas);
    }

    public function ideaComments($id){

        $checkID = $this->checkID($id);

        if($checkID){
            return $checkID;
        }

        $ideas = $this->ideaRepository->find($id);

        $comments = $ideas->comment()->where("hidden",false)->get();

        return CommentResource::collection($comments);

    }



    public function ideasToSubmit(Request $request)
    {

        $user_id = $request->user()->id;

        $ideaToSubmit = Idea::query();

        $ideaToSubmit->where('is_enabled', false)->whereHas('user.department',function($q) use($user_id){
            return $q->where('QACoordinatorID',$user_id);
        });

        $ideas = $ideaToSubmit->paginate(6);

        return IdeaResource::collection($ideas);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIdeaRequest $request)
    {


        $this->authorize("create",Idea::class);

        $activeSystemSetting = SystemSetting::query()->where("status", true)->first();
        $currentDate = now();

        if (!$activeSystemSetting) {
            return response()->json([
                'message' => 'You cannot create idea without active system setting'
            ], 409);
        }

        $ideaClosureDate = Carbon::parse($activeSystemSetting->idea_closure_date);

        if ($ideaClosureDate->lessThan($currentDate)) {
            return response()->json([
                'message' => 'You cannot create idea after the idea closure date'
            ], 409);
        }

        $checkCategory = $this->checkCategory($request->category);

        if ($checkCategory) {
            return $checkCategory;
        }

        $user = $request->user();


        $idea = $this->ideaRepository->create([...$request->all(), 'is_enabled' => false, 'user_id' => $user->id, 'system_setting_id' => $activeSystemSetting->id]);


        Mail::to($user->department->user[0]->email)->send(new PostIdeaMail($idea));

        return response()->json(['message' => 'Idea created successfully.', 'idea' => new IdeaResource($idea)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {

        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->find($id);


        return new IdeaResource($idea);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Idea $idea)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIdeaRequest $request, $id)
    {

        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->find($id);


        $this->authorize('update', $idea,Idea::class);


        $checkIdeaClosureDate = $this->ideaRepository->find($id);
        $ideaClosureDate = Carbon::parse($checkIdeaClosureDate->SystemSetting->idea_closure_date);
        $currentDate = now();

        if ($ideaClosureDate->lessThan($currentDate)) {
            return response()->json([
                'message' => 'You cannot update idea after the idea closure date'
            ], 409);
        }

        $checkCategory = $this->checkCategory($request->category);

        if ($checkCategory) {
            return $checkCategory;
        }


        $idea = $this->ideaRepository->update($id, [...$request->all(), 'is_enabled' => false]);

        return response()->json(['message' => 'Idea updated successfully.', 'idea' => new IdeaResource($idea)]);
    }

    public function updateIdeaCategory(UpdateIdeaCategoryRequest $request, $id)
    {

        // check the valid id or not
        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->find($id);

        $this->authorize("updateCategory",$idea,Idea::class);

        // check idea is over is over idea closure date or not.

        $checkIdeaClosureDate = $this->ideaRepository->find($id);
        $ideaClosureDate = Carbon::parse($checkIdeaClosureDate->SystemSetting->idea_closure_date);
        $currentDate = now();

        if ($ideaClosureDate->lessThan($currentDate)) {
            return response()->json([
                'message' => 'You cannot update category after the idea closure date'
            ], 409);
        }

        $checkCategory = $this->checkCategory($request->category);

        if ($checkCategory) {
            return $checkCategory;
        }

        $idea = $this->ideaRepository->updateIdeaCategory($id, $request->all());

        return response()->json(['message' => "Idea's Category updated successfully.", 'idea' => new IdeaResource($idea)]);
    }

    public function submitIdea(SubmitIdeaRequest $request, $id)
    {
        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->find($id);

        $this->authorize("submitIdea",$idea,Idea::class);

        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->submitIdea($id, $request->all());

        if($idea->is_enabled){

            Mail::to($idea->user->email)->send(new ApproveMail($idea));

        }

        return response()->json(['message' => "Idea's submitted successfully.", 'idea' => new IdeaResource($idea)]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request,$id)
    {
        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $idea = $this->ideaRepository->find($id);

        $this->authorize("delete",$idea,Idea::class);

        $idea = $this->ideaRepository->destroy($id);

        return response()->json(['message' => 'Idea deleted successfully.']);
    }
}
