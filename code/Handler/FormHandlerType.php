<?php

namespace SilverStripe\UserForms\Handler;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataList;

/**
 * Represents a configurable type of handler to help configure a user form.
 *  - getParamFields() will be used to build a configuration UI
 *  - getFormHandler() will return the resulting FormHandler object created from this configuration
 */
interface FormHandlerType
{

    /**
     * Return the title of this handler type for admin UIs
     */
    public function getTitle();

    /**
     * Return a list of fields for configuring this snippet.
     * The each field should return a scalar value (sorry, no GridFields)
     *
     * @return FieldList
     */
    public function getParamFields(DataList $userFormFields);

    /**
     * Return a list of fields for configuring this snippet.
     * The each field should return a scalar value (sorry, no GridFields)
     *
     * @return FormHandler
     */
    public function getFormHandler(array $params, DataList $userFormFields);
}
