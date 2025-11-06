<?php

namespace App\Mail;

use App\Models\Survey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyThankYou extends Mailable
{
    use Queueable, SerializesModels;

    public Survey $survey;

    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
    }

    public function build(): self
    {
        return $this->subject('Thank you for your feedback')
            ->markdown('emails.survey_thank_you', [
                'survey' => $this->survey,
            ]);
    }
}
