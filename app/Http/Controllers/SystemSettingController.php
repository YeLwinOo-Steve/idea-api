<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemSettingResouce;
use App\Http\Requests\StoreSystemSettingRequest;
use App\Http\Requests\UpdateSystemSettingRequest;
use App\Models\SystemSetting;
use App\Repositories\SystemSettingRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class SystemSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */

     use AuthorizesRequests;
    protected $systemSettingRepository;

    public function __construct(SystemSettingRepository $systemSettingRepository)
    {
        $this->systemSettingRepository = $systemSettingRepository;
    }

    protected function checkID($id)
    {
        $validated = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:system_settings,id',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'message' => 'Invalid system setting ID'
            ], 404);
        }

        return null;
    }

    public function index()
    {
        $systemSettings = SystemSetting::all();
        return SystemSettingResouce::collection($systemSettings);
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
    public function store(StoreSystemSettingRequest $request)
    {

        $this->authorize("checkPermission",SystemSetting::class);

        $activeSystemSetting = SystemSetting::query()->where("status", true)->exists();

        if ($activeSystemSetting) {
            return response()->json([
                'message' => 'Already have an active system setting'
            ], 409);
        }

        $systemSetting = $this->systemSettingRepository->create([...$request->all(), "status" => true]);

        return $systemSetting;

        return response()->json(['message' => 'System setting created successfully.', 'system_setting' => new SystemSettingResouce($systemSetting)], 201);
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

        $systemSetting = $this->systemSettingRepository->find($id);
        return response()->json(['system_settings' => $systemSetting]);
    }

    public function exportCSV($id)
    {
        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $systemSetting = $this->systemSettingRepository->find($id);

        if($systemSetting->status){
            return response()->json([
                "message" => "Cannot export CSV file before the final closure date"
            ],403);
        }

        $ideas = $systemSetting->ideas;

        $filename = "Academic Year " . $systemSetting->academic_year . " Ideas" . ".csv";

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($ideas) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Staff Name', 'Email', 'Department', 'Title', 'Content', 'Category', 'Files', 'Created At']);

            foreach ($ideas as $idea) {

                $name = $idea->is_anonymous ? "Anonymous" : $idea->user->name;

                $email = $idea->is_anonymous ? null : $idea->user->email;

                $department_name =  $idea->user->department ? $idea->user->department->department_name : null;

                $department = $idea->is_anonymous ? null : $department_name;

                $categories = implode(",", $idea->categories->pluck("name")->toArray());

                $files = implode(",", $idea->files->pluck("file_name")->toArray());

                $formattedDate = Carbon::parse($idea->created_at)->format('d F Y H:i');

                fputcsv($file, [$idea->id, $name, $email, $department, $idea->title, $idea->content, $categories, $files, $formattedDate]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SystemSetting $systemSetting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSystemSettingRequest $request, $id)
    {

        $this->authorize("checkPermission",SystemSetting::class);


        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $updateSystemSetting = $this->systemSettingRepository->find($id);

        if ($updateSystemSetting->status !== 1) {
            return response()->json([
                'message' => 'You can only edit active system setting'
            ], 409);
        }

        $systemSetting = $this->systemSettingRepository->update($id, $request->all());

        return response()->json(['message' => 'System setting updated successfully.', 'system_setting' => $systemSetting]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {

        $this->authorize("checkPermission",SystemSetting::class);

        $checkID = $this->checkID($id);

        if ($checkID) {
            return $checkID;
        }

        $checkCanDelete = $this->systemSettingRepository->find($id);

        $noOfIdeaUsed = $checkCanDelete->ideas->count();


        if ($noOfIdeaUsed) {
            return response()->json(['message' => 'System setting is used in Idea. Cannot delete system setting']);
        }

        $systemSetting = $this->systemSettingRepository->destroy($id);
        return response()->json(['message' => 'System setting deleted successfully.']);
    }
}
