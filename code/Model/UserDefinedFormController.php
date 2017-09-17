<?php

namespace SilverStripe\UserForms\Model;

use PageController;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Upload;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTP;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\Form;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;
use SilverStripe\UserForms\Form\UserForm;
use SilverStripe\UserForms\Model\EditableFormField\EditableFileField;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\UserForms\Handler\FormDefaultProvider;

/**
 * Controller for the {@link UserDefinedForm} page type.
 *
 * @package userforms
 */
class UserDefinedFormController extends PageController
{
    private static $finished_anchor = '#uff';

    private static $allowed_actions = [
        'index',
        'ping',
        'Form',
        'finished'
    ];

    protected function init()
    {
        parent::init();

        $page = $this->data();

        $userforms = ModuleLoader::getModule('silverstripe/userforms');
        $admin = ModuleLoader::getModule('silverstripe/admin');
        // load the css
        if (!$page->config()->get('block_default_userforms_css')) {
            Requirements::css($userforms->getRelativeResourcePath('client/dist/styles/userforms.css'));
        }

        // load the jquery
        if (!$page->config()->get('block_default_userforms_js')) {
            Requirements::javascript('//code.jquery.com/jquery-1.7.2.min.js');
            Requirements::javascript(
                $userforms->getRelativeResourcePath('client/thirdparty/jquery-validate/jquery.validate.min.js')
            );
            Requirements::add_i18n_javascript($userforms->getRelativeResourcePath('client/lang'));
            Requirements::javascript($userforms->getRelativeResourcePath('client/dist/js/userforms.js'));

            $this->addUserFormsValidatei18n($userforms);

            // Bind a confirmation message when navigating away from a partially completed form.
            if ($page::config()->get('enable_are_you_sure')) {
                Requirements::javascript(
                    $userforms->getRelativeResourcePath('client/thirdparty/jquery.are-you-sure/jquery.are-you-sure.js')
                );
            }
        }
    }

    /**
     * Add the necessary jQuery validate i18n translation files, either by locale or by langauge,
     * e.g. 'en_NZ' or 'en'. This adds "methods_abc.min.js" as well as "messages_abc.min.js" from the
     * jQuery validate thirdparty library.
     *
     * @param Module $module
     */
    protected function addUserFormsValidatei18n(Module $module)
    {
        $candidates = [
            i18n::getData()->langFromLocale(i18n::config()->get('default_locale')),
            i18n::config()->get('default_locale'),
            i18n::getData()->langFromLocale(i18n::get_locale()),
            i18n::get_locale(),
        ];

        foreach ($candidates as $candidate) {
            foreach (['messages', 'methods'] as $candidateType) {
                $localisationCandidate = "client/thirdparty/jquery-validate/localization/{$candidateType}_{$candidate}.min.js";
                if ($module->hasResource($localisationCandidate)) {
                    Requirements::javascript($module->getRelativeResourcePath($localisationCandidate));
                }
            }
        }
    }

    /**
     * Using $UserDefinedForm in the Content area of the page shows
     * where the form should be rendered into. If it does not exist
     * then default back to $Form.
     *
     * @return array
     */
    public function index()
    {
        if ($this->Content && $form = $this->Form()) {
            $hasLocation = stristr($this->Content, '$UserDefinedForm');
            if ($hasLocation) {
                $content = preg_replace(
                    '/(<p[^>]*>)?\\$UserDefinedForm(<\\/p>)?/i',
                    $form->forTemplate(),
                    $this->Content
                );
                return [
                    'Content' => DBField::create_field('HTMLText', $content),
                    'Form' => ''
                ];
            }
        }

        return [
            'Content' => DBField::create_field('HTMLText', $this->Content),
            'Form' => $this->Form()
        ];
    }

    /**
     * Keep the session alive for the user.
     *
     * @return int
     */
    public function ping()
    {
        return 1;
    }

    /**
     * Get the form for the page. Form can be modified by calling {@link updateForm()}
     * on a UserDefinedForm extension.
     *
     * @return Form
     */
    public function Form()
    {
        $form = UserForm::create($this, 'Form_' . $this->ID);
        /** @skipUpgrade */
        $form->setFormAction(Controller::join_links($this->Link(), 'Form'));
        $this->generateConditionalJavascript();
        return $form;
    }

    /**
     * Generate the javascript for the conditional field show / hiding logic.
     *
     * @return void
     */
    public function generateConditionalJavascript()
    {
        $default = '';
        $rules = '';

        $watch = [];

        if ($this->Fields()) {
            /** @var EditableFormField $field */
            foreach ($this->Fields() as $field) {
                if ($result = $field->formatDisplayRules()) {
                    $watch[] = $result;
                }
            }
        }
        if ($watch) {
            $rules .= $this->buildWatchJS($watch);
        }

        // Only add customScript if $default or $rules is defined
        if ($rules) {
            Requirements::customScript(<<<JS
                (function($) {
                    $(document).ready(function() {
                        {$rules}
                    });
                })(jQuery);
JS
            , 'UserFormsConditional');
        }
    }

    /**
     * Process the form that is submitted through the site
     *
     * {@see UserForm::validate()} for validation step prior to processing
     *
     * @param array $data
     * @param Form $form
     *
     * @return \SilverStripe\Control\HTTPResponse
     */
    public function process($data, $form)
    {
        $submittedForm = SubmittedForm::create();
        $submittedForm->SubmittedByID = Security::getCurrentUser() ? Security::getCurrentUser()->ID : 0;
        $submittedForm->ParentID = $this->ID;

        // if saving is not disabled save now to generate the ID
        if (!$this->DisableSaveSubmissions) {
            $submittedForm->write();
        }

        $submittedFields = ArrayList::create();
        $handlerData = [];

        foreach ($this->Fields() as $field) {
            if (!$field->showInReports()) {
                continue;
            }

            $submittedField = $field->getSubmittedFormField();
            $submittedField->ParentID = $submittedForm->ID;
            $submittedField->Name = $field->Name;
            $submittedField->Title = $field->getField('Title');

            // save the value from the data
            if ($field->hasMethod('getValueFromData')) {
                $submittedField->Value = $field->getValueFromData($data);
            } else {
                if (isset($data[$field->Name])) {
                    $submittedField->Value = $data[$field->Name];
                }
            }

            $handlerData[$field->Name] = $data[$field->Name];

            if (!empty($data[$field->Name])) {
                if (in_array(EditableFileField::class, $field->getClassAncestry())) {
                    if (!empty($_FILES[$field->Name]['name'])) {
                        $foldername = $field->getFormField()->getFolderName();

                        // create the file from post data
                        $upload = Upload::create();
                        $file = File::create();
                        $file->ShowInSearch = 0;
                        try {
                            $upload->loadIntoFile($_FILES[$field->Name], $file, $foldername);
                        } catch (ValidationException $e) {
                            $validationResult = $e->getResult();
                            $form->addErrorMessage($field->Name, $validationResult->message(), 'bad');
                            Controller::curr()->redirectBack();
                            return;
                        }

                        // write file to form field
                        $submittedField->UploadedFileID = $file->ID;
                        $handlerData[$field->Name] = $file;
                    }
                }
            }

            $submittedField->extend('onPopulationFromField', $field);

            if (!$this->DisableSaveSubmissions) {
                $submittedField->write();
            }

            $submittedFields->push($submittedField);
        }

        foreach ($this->dataRecord->ActionHandlers() as $handlerRecord) {
            if ($handler = $handlerRecord->getHandler()) {
                $handler->runHandler($handlerData);
            }
        }

        $submittedForm->extend('updateAfterProcess');

        $session = $this->getRequest()->getSession();
        $session->clear("FormInfo.{$form->FormName()}.errors");
        $session->clear("FormInfo.{$form->FormName()}.data");

        $referrer = (isset($data['Referrer'])) ? '?referrer=' . urlencode($data['Referrer']) : "";

        // set a session variable from the security ID to stop people accessing
        // the finished method directly.
        if (!$this->DisableAuthenicatedFinishAction) {
            if (isset($data['SecurityID'])) {
                $session->set('FormProcessed', $data['SecurityID']);
            } else {
                // if the form has had tokens disabled we still need to set FormProcessed
                // to allow us to get through the finshed method
                if (!$this->Form()->getSecurityToken()->isEnabled()) {
                    $randNum = rand(1, 1000);
                    $randHash = md5($randNum);
                    $session->set('FormProcessed', $randHash);
                    $session->set('FormProcessedNum', $randNum);
                }
            }
        }

        if (!$this->DisableSaveSubmissions) {
            $session->set('userformssubmission'. $this->ID, $submittedForm->ID);
        }

        return $this->redirect($this->Link('finished') . $referrer . $this->config()->get('finished_anchor'));
    }

    /**
     * Return the default form data supplied by the handler, if any
     *
     * @return array
     */
    public function getHandlerDefaultFormData()
    {
        $defaults = [];
        foreach ($this->dataRecord->ActionHandlers() as $handlerRecord) {
            $handler = $handlerRecord->getHandler();
            if ($handler && $handler instanceof FormDefaultProvider) {
                $defaults = array_merge($defaults, (array)$handler->getDefaults($this->dataRecord->Fields()));
            }
        }
        return $defaults;
    }

    /**
     * This action handles rendering the "finished" message, which is
     * customizable by editing the ReceivedFormSubmission template.
     *
     * @return ViewableData
     */
    public function finished()
    {
        $submission = $this->getRequest()->getSession()->get('userformssubmission'. $this->ID);

        if ($submission) {
            $submission = SubmittedForm::get()->byId($submission);
        }

        $referrer = isset($_GET['referrer']) ? urldecode($_GET['referrer']) : null;

        if (!$this->DisableAuthenicatedFinishAction) {
            $formProcessed = $this->getRequest()->getSession()->get('FormProcessed');

            if (!isset($formProcessed)) {
                return $this->redirect($this->Link() . $referrer);
            } else {
                $securityID = $this->getRequest()->getSession()->get('SecurityID');
                // make sure the session matches the SecurityID and is not left over from another form
                if ($formProcessed != $securityID) {
                    // they may have disabled tokens on the form
                    $securityID = md5($this->getRequest()->getSession()->get('FormProcessedNum'));
                    if ($formProcessed != $securityID) {
                        return $this->redirect($this->Link() . $referrer);
                    }
                }
            }

            $this->getRequest()->getSession()->clear('FormProcessed');
        }

        $data = [
            'Submission' => $submission,
            'Link' => $referrer
        ];

        $this->extend('updateReceivedFormSubmissionData', $data);

        return $this->customise([
            'Content' => $this->customise($data)->renderWith(__CLASS__ . '_ReceivedFormSubmission'),
            'Form' => '',
        ]);
    }

    /**
     * Outputs the required JS from the $watch input
     *
     * @param array $watch
     *
     * @return string
     */
    protected function buildWatchJS($watch)
    {
        $result = '';
        foreach ($watch as $key => $rule) {
            $events = implode(' ', $rule['events']);
            $selectors = implode(', ', $rule['selectors']);
            $conjunction = $rule['conjunction'];
            $operations = implode(" {$conjunction} ", $rule['operations']);
            $target = $rule['targetFieldID'];

            $result .= <<<EOS
\n
    $('.userform').on('{$events}',
    "{$selectors}",
    function (){
        if ({$operations}) {
            $('{$target}').{$rule['view']};
        } else {
            $('{$target}').{$rule['opposite']};
        }
    });
    $("{$target}").find('.hide').removeClass('hide');
EOS;
        }

        return $result;
    }
}
