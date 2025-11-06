<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // true = anyone can submit this form (it's a public survey)
        return true;
    }

    public function rules(): array
    {
        return [
            // From page-services
            'service' => 'required|string|max:255',
            
            // From page-client-info
            'clientInfo' => 'required|array',
            'clientInfo.name' => 'nullable|string|max:255',
            'clientInfo.age' => 'nullable|string|max:10',
            'clientInfo.barangay' => 'nullable|string|max:255',
            'clientInfo.email' => 'nullable|email|max:255',
            'clientInfo.phone' => 'nullable|string|max:20',
            
            // From page-final-comments
            'comments' => 'nullable|string|max:500',
            
            // From rating pages
            'ratings' => 'required|array|min:8', // Make sure all ratings are present
            'ratings.A1' => 'required|string',
            'ratings.A2' => 'required|string',
            'ratings.A3' => 'required|string',
            'ratings.A4' => 'required|string',
            'ratings.B1' => 'required|string',
            'ratings.B2' => 'required|string',
            'ratings.C1' => 'required|string',
            'ratings.C2' => 'required|string',
            'ratings.C3' => 'required|string',
        ];
    }
}
