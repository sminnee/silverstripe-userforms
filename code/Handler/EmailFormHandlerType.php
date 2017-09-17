<?php

namespace SilverStripe\UserForms\Handler;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataList;
use SilverStripe\View\Requirements;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Assets\FileFinder;
use SilverStripe\UserForms\Model\UserDefinedForm;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\EditableFormField\EditableMultipleOptionField;
use SilverStripe\UserForms\Model\EditableFormField\EditableTextField;


class EmailFormHandlerType implements FormHandlerType
{

    public function getTitle()
    {
        return "Send an email";
    }

    public function getFormHandler(array $params, DataList $userFormFields)
    {
        return new EmailFormHandler($params, $this);
    }

    /**
     * @return FieldList
     */
    public function getParamFields(DataList $userFormFields)
    {
        Requirements::javascript('silverstripe/userforms: client/dist/js/userforms-cms.js');

        // predefined choices are also candidates
        $multiOptionFields = $userFormFields->filter(['ClassName' => EditableMultipleOptionField::class]);

        // if they have email fields then we could send from it
        $validEmailFromFields = $userFormFields->filter(['ClassName' => EditableEmailField::class]);

        // For the subject, only one-line entry boxes make sense
        $validSubjectFields = ArrayList::create(
            $userFormFields
                ->filter(['ClassName' => EditableTextField::class])
                //->exclude('Rows:GreaterThan', 1)
                ->toArray()
        );
        $validSubjectFields->merge($multiOptionFields);

        // Check valid email-recipient fields
        if (Config::inst()->get(EmailFormHandler::class, 'allow_unbound_recipient_fields')) {
            // To address can only be email fields or multi option fields
            $validEmailToFields = ArrayList::create($validEmailFromFields->toArray());
            $validEmailToFields->merge($multiOptionFields);
        } else {
            // To address cannot be unbound, so restrict to pre-defined lists
            $validEmailToFields = $multiOptionFields;
        }

        // Build fieldlist
        $fields = FieldList::create(Tabset::create('Root')->addExtraClass('EmailRecipientForm'));

        // Configuration fields
        $fields->addFieldsToTab('Root.Main', [
            // Subject
            FieldGroup::create(
                TextField::create(
                    'EmailSubject',
                    _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.TYPESUBJECT', 'Type subject')
                )
                    ->setAttribute('style', 'min-width: 400px;'),
                DropdownField::create(
                    'SendEmailSubjectFieldID',
                    _t(
                        'SilverStripe\\UserForms\\Model\\UserDefinedForm.SELECTAFIELDTOSETSUBJECT',
                        '.. or select a field to use as the subject'
                    ),
                    $validSubjectFields->map('ID', 'Title')
                )->setEmptyString('')
            )
                ->setTitle(_t('SilverStripe\\UserForms\\Model\\UserDefinedForm.EMAILSUBJECT', 'Email subject')),

            // To
            FieldGroup::create(
                TextField::create(
                    'EmailAddress',
                    _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.TYPETO', 'Type to address')
                )
                    ->setAttribute('style', 'min-width: 400px;'),
                DropdownField::create(
                    'SendEmailToFieldID',
                    _t(
                        'SilverStripe\\UserForms\\Model\\UserDefinedForm.ORSELECTAFIELDTOUSEASTO',
                        '.. or select a field to use as the to address'
                    ),
                    $validEmailToFields->map('ID', 'Title')
                )->setEmptyString(' ')
            )
                ->setTitle(_t('SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDEMAILTO', 'Send email to'))
                ->setDescription(_t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDEMAILTO_DESCRIPTION',
                    'You may enter multiple email addresses as a comma separated list.'
                )),


            // From
            TextField::create(
                'EmailFrom',
                _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.FROMADDRESS', 'Send email from')
            )
                ->setDescription(_t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.EmailFromContent',
                    "The from address allows you to set who the email comes from. On most servers this ".
                    "will need to be set to an email address on the same domain name as your site. ".
                    "For example on yoursite.com the from address may need to be something@yoursite.com. ".
                    "You can however, set any email address you wish as the reply to address."
                )),


            // Reply-To
            FieldGroup::create(
                TextField::create('EmailReplyTo', _t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.TYPEREPLY',
                    'Type reply address'
                ))
                    ->setAttribute('style', 'min-width: 400px;'),
                DropdownField::create(
                    'SendEmailFromFieldID',
                    _t(
                        'SilverStripe\\UserForms\\Model\\UserDefinedForm.ORSELECTAFIELDTOUSEASFROM',
                        '.. or select a field to use as reply to address'
                    ),
                    $validEmailFromFields->map('ID', 'Title')
                )->setEmptyString(' ')
            )
                ->setTitle(_t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.REPLYADDRESS',
                    'Email for reply to'
                ))
                ->setDescription(_t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.REPLYADDRESS_DESCRIPTION',
                    'The email address which the recipient is able to \'reply\' to.'
                ))
        ]);

        $fields->fieldByName('Root.Main')->setTitle(_t(__CLASS__.'.EMAILDETAILSTAB', 'Email Details'));

        // TO DO: find a better place to put this functionality
        // // Only show the preview link if the recipient has been saved.
        // if (!empty($this->EmailTemplate)) {
        //     $pageEditController = singleton(CMSPageEditController::class);
        //     $pageEditController
        //         ->getRequest()
        //         ->setSession(Controller::curr()->getRequest()->getSession());

        //     $preview = sprintf(
        //         '<p><a href="%s" target="_blank" class="btn btn-outline-secondary">%s</a></p><em>%s</em>',
        //         Controller::join_links(
        //             $pageEditController->getEditForm()->FormAction(),
        //             "field/EmailRecipients/item/{$this->ID}/preview"
        //         ),
        //         _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.PREVIEW_EMAIL', 'Preview email'),
        //         _t(
        //             'SilverStripe\\UserForms\\Model\\UserDefinedForm.PREVIEW_EMAIL_DESCRIPTION',
        //             'Note: Unsaved changes will not appear in the preview.'
        //         )
        //     );
        // } else {
        //     $preview = sprintf(
        //         '<em>%s</em>',
        //         _t(
        //             'SilverStripe\\UserForms\\Model\\UserDefinedForm.PREVIEW_EMAIL_UNAVAILABLE',
        //             'You can preview this email once you have saved the Recipient.'
        //         )
        //     );
        // }

        // Email templates
        $fields->addFieldsToTab('Root.EmailContent', [
            CheckboxField::create(
                'HideFormData',
                _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.HIDEFORMDATA', 'Hide form data from email?')
            ),
            CheckboxField::create(
                'SendPlain',
                _t(
                    'SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDPLAIN',
                    'Send email as plain text? (HTML will be stripped)'
                )
            ),
            DropdownField::create(
                'EmailTemplate',
                _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.EMAILTEMPLATE', 'Email template'),
                $this->getEmailTemplateDropdownValues()
            )->addExtraClass('toggle-html-only'),
            HTMLEditorField::create(
                'EmailBodyHtml',
                _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.EMAILBODYHTML', 'Body')
            )
                ->addExtraClass('toggle-html-only'),
            TextareaField::create(
                'EmailBody',
                _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.EMAILBODY', 'Body')
            )
                ->addExtraClass('toggle-plain-only')//,
            //LiteralField::create('EmailPreview', $preview)
        ]);

        $fields->fieldByName('Root.EmailContent')->setTitle(_t(__CLASS__.'.EMAILCONTENTTAB', 'Email Content'));

        // TO DO: move to UserFormHandler
        // // Custom rules for sending this field
        // $grid = GridField::create(
        //     'CustomRules',
        //     _t('SilverStripe\\UserForms\\Model\\EditableFormField.CUSTOMRULES', 'Custom Rules'),
        //     $this->CustomRules(),
        //     $this->getRulesConfig()
        // );
        // $grid->setDescription(_t(
        //     'SilverStripe\\UserForms\\Model\\UserDefinedForm.RulesDescription',
        //     'Emails will only be sent to the recipient if the custom rules are met. If no rules are defined, this receipient will receive notifications for every submission.'
        // ));
        // $fields->addFieldsToTab('Root.CustomRules', [
        //     DropdownField::create(
        //         'CustomRulesCondition',
        //         _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDIF', 'Send condition'),
        //         [
        //             'Or' => _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDIFOR', 'Any conditions are true'),
        //             'And' => _t('SilverStripe\\UserForms\\Model\\UserDefinedForm.SENDIFAND', 'All conditions are true')
        //         ]
        //     ),
        //     $grid
        // ]);

        // $fields->fieldByName('Root.CustomRules')->setTitle(_t(__CLASS__.'.CUSTOMRULESTAB', 'Custom Rules'));

        // $this->extend('updateCMSFields', $fields);
        return $fields;
    }


    /**
     * Gets a list of email templates suitable for populating the email template dropdown.
     *
     * @return array
     */
    public function getEmailTemplateDropdownValues()
    {
        $templates = [];

        $finder = new FileFinder();
        $finder->setOption('name_regex', '/^.*\.ss$/');

        $templateDirectory = UserDefinedForm::config()->get('email_template_directory');
        // Handle cases where "userforms" might not be the base module directory, e.g. in a Travis build
        if (!file_exists(BASE_PATH . DIRECTORY_SEPARATOR . $templateDirectory)
            && substr($templateDirectory, 0, 10) === 'userforms/'
        ) {
            $templateDirectory = substr($templateDirectory, 10);
        }
        $found = $finder->find(BASE_PATH . DIRECTORY_SEPARATOR . $templateDirectory);

        foreach ($found as $key => $value) {
            $template = pathinfo($value);
            $templatePath = substr(
                $template['dirname'] . DIRECTORY_SEPARATOR . $template['filename'],
                strlen(BASE_PATH) + 1
            );

            $defaultPrefixes = ['userforms/templates/', 'templates/'];
            foreach ($defaultPrefixes as $defaultPrefix) {
                // Remove default userforms folder if it's provided
                if (substr($templatePath, 0, strlen($defaultPrefix)) === $defaultPrefix) {
                    $templatePath = substr($templatePath, strlen($defaultPrefix));
                    break;
                }
            }
            $templates[$templatePath] = $template['filename'];
        }

        return $templates;
    }
}
