<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyAnswerRequest;
use App\Models\Survey;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Http\Resources\SurveyResource;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return SurveyResource::collection(Survey::where('user_id', $user->id)
            ->withCount(['questions', 'answers'])
            ->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSurveyRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSurveyRequest $request)
    {
        $attributes = $request->validated();

        if (isset($request->image)) {

            $name = time() . '.' . explode('/', explode(':', substr($request->image, 0, strpos($request->image, ';')))[1])[1];
            $name = "/images/{$name}";
            \Image::make($request->image)->save(public_path($name));

            $attributes['image'] = $name;
        }

        $survey =  Survey::create($attributes);

        foreach ($attributes['questions'] as $question) {
            $question['survey_id'] = $survey->id;
            $this->createQuestion($question);
        }

        return new SurveyResource($survey);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function show(Survey $survey, Request $request)
    {
        $user = $request->user();

        if ($user->id !== $survey->user_id) {
            return abort(403, "Unauthorized action");
        }

        $survey->questions;

        return new SurveyResource($survey);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSurveyRequest  $request
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSurveyRequest $request, Survey $survey)
    {
        $attributes = $request->validated();

        if (isset($request->image) && ($request->image !== URL::to($survey->image))) {
            $name = time() . '.' . explode('/', explode(':', substr($request->image, 0, strpos($request->image, ';')))[1])[1];

            $name = "/images/{$name}";
            \Image::make($request->image)->save(public_path($name));

            $attributes['image'] = $name;

            File::delete(public_path($survey->image));
        }

        $survey->update($attributes);

        //Existing question ids.
        $existingQuestionIds =  $survey->questions()->pluck('id')->toArray();

        //New question ids.
        $newQuestionIds = Arr::pluck($attributes['questions'], 'id');

        //To be delete question ids.
        $toDelete = array_diff($existingQuestionIds, $newQuestionIds);

        //To be add question ids.
        $toAdd = array_diff($newQuestionIds, $existingQuestionIds);

        SurveyQuestion::destroy($toDelete);

        foreach ($attributes['questions'] as $question) {
            if (in_array($question['id'], $toAdd)) {
                $question['survey_id'] = $survey->id;
                $this->createQuestion($question);
            }
        }

        $questionMap = collect($attributes['questions'])->keyBy('id');


        foreach ($survey->questions as $question) {
            if (isset($questionMap[$question->id])) {
                $this->updateQuestion($question, $questionMap[$question->id]);
            }
        }

        return new SurveyResource($survey);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function destroy(Survey $survey, Request $request)
    {
        $user = $request->user();

        if ($user->id !== $survey->user_id) {
            return abort(403, "Unauthorized action");
        }

        $survey->delete();

        File::delete(public_path($survey->image));

        return response('', 204);
    }

    public function showForGuest(Survey $survey)
    {
        $survey->questions;
        return new SurveyResource($survey);
    }

    public function storeAnswers(StoreSurveyAnswerRequest $request, Survey $survey)
    {
        $attributes = $request->validated();

        $surveyAnswer = SurveyAnswer::create([
            'survey_id' => $survey->id,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s'),
        ]);


        foreach ($attributes['answers'] as $questionId => $answer) {
            $question = SurveyQuestion::where(['id' => $questionId, 'survey_id' => $survey->id])->get();

            if (!$question) {
                return response("Invalid question id", 404);
            }

            $data = [
                'survey_question_id' => $questionId,
                'survey_answer_id' => $surveyAnswer->id,
                'answer' => is_array($answer) ? json_encode($answer) : $answer
            ];

            SurveyQuestionAnswer::create($data);
        }

        return response("", 201);
    }

    private function createQuestion($question)
    {
        if (is_array($question['data'])) {
            $question['data'] = json_encode($question['data']);
        }

        $validator = Validator::make($question, [
            'question' => 'required|string',
            'type' => ['required', Rule::in([
                SurveyQuestion::TYPE_TEXT,
                SurveyQuestion::TYPE_TEXTAREA,
                SurveyQuestion::TYPE_SELECT,
                SurveyQuestion::TYPE_RADIO,
                SurveyQuestion::TYPE_CHECKBOX,

            ])],
            'description' => 'nullable|string',
            'data' => 'present',
            'survey_id' => 'exists:surveys,id'
        ]);

        return SurveyQuestion::create($validator->validated());
    }

    private function updateQuestion(SurveyQuestion $question, $updatedQuestion)
    {
        if (($updatedQuestion['data'])) {
            $updatedQuestion['data'] = json_encode($updatedQuestion['data']);
        }

        $validator = Validator::make($updatedQuestion, [
            'id' => 'exists:survey_questions,id',
            'question' => 'required|string',
            'type' => ['required', Rule::in([
                SurveyQuestion::TYPE_TEXT,
                SurveyQuestion::TYPE_TEXTAREA,
                SurveyQuestion::TYPE_SELECT,
                SurveyQuestion::TYPE_RADIO,
                SurveyQuestion::TYPE_CHECKBOX,

            ])],
            'description' => 'nullable|string',
            'data' => 'present',
        ]);

        return $question->update($validator->validated());
    }
}
