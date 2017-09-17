<?php

namespace SilverStripe\UserForms\Handler;

use SilverStripe\Assets\File;
use SilverStripe\Control\Email\Email;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class EmailFormHandler implements FormHandler
{

    protected $params;
    protected $type;

    public function __construct($params, EmailFormHandlerType $type)
    {
        $this->params = array_merge(
            [
                'EmailAddress' => null,
                'EmailSubject' => null,
                'EmailBodyContent' => null,
            ],
            $params
        );
        $this->type = $type;
    }

    public function getSummary()
    {
        return "Send an email to '" . $this->params['EmailAddress'] . "', subject: '" . $this->params['EmailSubject'] . "'";
    }

    public function runHandler(array $data)
    {
        // TODO
        $humanData = $data;

        // email users on submit.
        $email = Email::create()
            ->setHTMLTemplate('email/SubmittedFormEmail.ss')
            ->setPlainTemplate('email/SubmittedFormEmail.ss');

        $mergeFields = new ArrayData($humanData);

        // Process attachments - files of < 1MB
        foreach ($data as $k => $file) {
            if ($file instanceof File && $file->ID > 0 && $file->getAbsoluteSize() < 1024 * 1024 * 1) {
                $email->attachFile(
                    $file->Filename,
                    $file->Filename,
                    HTTP::get_mime_type($file->Filename)
                );
            }
        }

        $parsedBody = SSViewer::execute_string($this->params['EmailBodyContent'], $mergeFields);

        if (!$this->params['SendPlain'] && $this->emailTemplateExists()) {
            $email->setHTMLTemplate($this->params['EmailTemplate']);
        }

        // TODO
        // $email->setData($recipient);
        // foreach ($emailData as $key => $value) {
        //     $email->addData($key, $value);
        // }

        $email->setFrom($this->params['EmailFrom']);
        $email->setBody($parsedBody);
        $email->setTo($this->params['EmailAddress']);
        $email->setSubject($this->params['EmailSubject']);

        if (!empty($this->params['EmailReplyTo'])) {
            $email->setReplyTo($this->params['EmailReplyTo']);
        }

        // // check to see if they are a dynamic reply to. eg based on a email field a user selected
        // if ($recipient->SendEmailFromField()) {
        //     $submittedFormField = $submittedFields->find('Name', $recipient->SendEmailFromField()->Name);

        //     if ($submittedFormField && is_string($submittedFormField->Value)) {
        //         $email->setReplyTo($submittedFormField->Value);
        //     }
        // }
        // // check to see if they are a dynamic reciever eg based on a dropdown field a user selected
        // if ($recipient->SendEmailToField()) {
        //     $submittedFormField = $submittedFields->find('Name', $recipient->SendEmailToField()->Name);

        //     if ($submittedFormField && is_string($submittedFormField->Value)) {
        //         $email->setTo($submittedFormField->Value);
        //     }
        // }

        // // check to see if there is a dynamic subject
        // if ($recipient->SendEmailSubjectField()) {
        //     $submittedFormField = $submittedFields->find('Name', $recipient->SendEmailSubjectField()->Name);

        //     if ($submittedFormField && trim($submittedFormField->Value)) {
        //         $email->setSubject($submittedFormField->Value);
        //     }
        // }

        // $this->extend('updateEmail', $email, $recipient, $emailData);

        if ($this->params['SendPlain']) {
            $body = strip_tags(DBField::create_field('HTMLText', $this->parans['EmailBody'])->Plain()).  "\n";
            // if (isset($emailData['Fields']) && !$recipient->HideFormData) {
            //     foreach ($emailData['Fields'] as $Field) {
            //         $body .= $Field->Title . ': ' . $Field->Value . " \n";
            //     }
            // }

            $email->setBody($body);
            $email->sendPlain();
        } else {
            $email->send();
        }
    }

    /**
     * Make sure the email template saved against the recipient exists on the file system.
     *
     * @param string
     *
     * @return boolean
     */
    protected function emailTemplateExists($template = '')
    {
        $t = ($template ? $template : $this->params['EmailTemplate']);
        return array_key_exists($t, $this->type->getEmailTemplateDropdownValues());
    }
}
