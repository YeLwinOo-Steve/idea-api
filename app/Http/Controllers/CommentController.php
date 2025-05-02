<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommentResource;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Mail\CommentMail;
use App\Models\Comment;
use App\Repositories\CommentRepository;
use App\Repositories\IdeaRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     use AuthorizesRequests;
     protected $commentRepository;
     protected $ideaRepository;


    public function __construct(CommentRepository $commentRepository,IdeaRepository $ideaRepository)
    {
        $this->commentRepository = $commentRepository;
        $this->ideaRepository = $ideaRepository;
    }

    protected function checkID($id){
        $validated = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:comments,id',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Invalid Comment ID'
            ], 404);
        }

        return null;
    }

    public function index()
    {
        $comments = Comment::all();
        return CommentResource::collection($comments);
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
    public function store(StoreCommentRequest $request)
    {

        $this->authorize("create",Comment::class);

        $checkIdeaFinalClosureDate = $this->ideaRepository->find($request->idea_id);

        if (!$checkIdeaFinalClosureDate->SystemSetting->status) {
            return response()->json([
                'message' => 'Cannot comment idea after the final closure date'
            ], 409);
        }


        $comment = $this->commentRepository->create([...$request->all(),"user_id" => $request->user()->id]);


        Mail::to($comment->idea->user->email)->send(new CommentMail($comment));


        return response()->json(['message' => 'Comment created successfully.', 'comment' => new CommentResource($comment)], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Comment $comment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCommentRequest $request, $id)
    {
        $checkID = $this->checkID($id);

        if($checkID){
            return $checkID;
        }

        $comment = $this->commentRepository->find($id);

        $this->authorize("update",$comment);

        $checkIdeaFinalClosureDate = $this->ideaRepository->find($request->idea_id);

        if (!$checkIdeaFinalClosureDate->SystemSetting->status) {
            return response()->json([
                'message' => 'Cannot comment idea after the final closure date'
            ], 409);
        }

        $comment = $this->commentRepository->update($id, [...$request->all(),"user_id" => $request->user()->id]);
        return response()->json(['message' => 'Comment updated successfully.', 'comments' => $comment]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $checkID = $this->checkID($id);

        if($checkID){
            return $checkID;
        }

        $comment = $this->commentRepository->find($id);

        $this->authorize("delete",$comment);

        $comment = $this->commentRepository->destroy($id);
        return response()->json(['message' => 'Comment deleted successfully.']);
    }
}
