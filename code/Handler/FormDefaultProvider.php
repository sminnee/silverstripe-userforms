<?php

namespace SilverStripe\UserForms\Handler;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataList;

/**
 * Represents a special kind of FormHandler that also provides default values for the form.
 * This could be used to build a form that edits some data rather than simply submitting it.
 */
interface FormDefaultProvider extends FormHandler
{

    /**
     * Provide default data for this form
     *
     * @param array $fieldNames An array of programmatic field names in this form
     * @param return array A map of form content, keyed by programmatic field names. Where fields are unrecognised,
     *                     they should be excluded from the array
     */
    public function getDefaults(DataList $userFormFields);
}
