<?php

namespace SilverStripe\UserForms\Handler;

use SilverStripe\Forms\FieldList;

/**
 * Represents a handler to determine the behaviour of a user form.
 *
 */
interface FormHandler
{

    /**
     * Return the title of this handler type for admin UIs
     */
    public function getSummary();

    /**
     * Execute this form handler, with the given form data
     *
     * @param array $data A map of form content, keyed by programmatic field names (e.g. "merge field")
     * @param return
     */
    public function runHandler(array $data);
}
