<?php

namespace SilverStripe\UserForms\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\UserForms\Extension\ParamExpander;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\UserForms\Handler\FormHandlerType;

/**
 * Represents a single configured action handler for a single user-form
 */
class UserDefinedFormAction extends DataObject
{

    use ParamExpander;

    private static $db = [
        "HandlerClass" => "Varchar(255)",
        "HandlerParams" => "Text",
    ];

    private static $has_one = [
        "Form" => UserDefinedForm::class,
    ];

    private static $summary_fields = [
        "Summary",
    ];

    /**
     * Return the snippet provider attached to this record
     */
    public function getHandlerType()
    {
        if ($this->HandlerClass && in_array(FormHandlerType::class, class_implements($this->HandlerClass))) {
            return Injector::inst()->get($this->HandlerClass);
        }
    }

    public function getHandler()
    {
        if ($type = $this->getHandlerType()) {
            return $type->getFormHandler((array)json_decode($this->HandlerParams, true), $this->Form()->Fields());
        }
    }

    protected function getSummary()
    {
        if ($handler = $this->getHandler()) {
            return $handler->getSummary();
        } else if ($type = $this->getHandlerType()) {
            return $type->getTitle();
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // $fields->dataFieldByName('Active')->setSource(self::$active_labels);

        $providerFields = null;
        if ($handler = $this->getHandlerType()) {
            $providerFields = $handler->getParamFields($this->Form()->Fields());
        }
        $this->expandParams('HandlerParams', $providerFields, $fields, 'Root.Main');

        return $fields;
    }
}
