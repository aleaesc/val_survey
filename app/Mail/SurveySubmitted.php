<?php

namespace App\Mail;

use App\Models\Survey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveySubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public Survey $survey;

    /**
     * Create a new message instance.
     */
    public function __construct(Survey $survey)
    {
        $this->survey = $survey;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('New Survey Submitted')
            ->markdown('emails.survey_submitted', [
                'survey' => $this->survey,
            ]);
    }
}
