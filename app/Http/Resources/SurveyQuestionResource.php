<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SurveyQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $data = is_array($this->data) ? json_encode($this->data) : $this->data;
        return [
            'id' => $this->id,
            'type' => $this->type,
            'question' => $this->question,
            'description' => $this->description,
            // 'data' => $this->data,
            'data' => json_decode($data),
        ];
    }
}
