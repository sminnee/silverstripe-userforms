<?php

namespace SilverStripe\UserForms\Extension;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TabSet;

/**
 * Provides support for adding JSON-packed parameters into a single DataObject field
 */
trait ParamExpander
{

    protected function expandParams($modelField, $paramFields, FieldList $fields, $tabName)
    {
        $fields->removeByName($modelField);
        if ($paramFields) {
            foreach ($paramFields->dataFields() as $field) {
                $name = "COMPOUND_{$modelField}_" . $field->getName();
                $field->setName($name);
                $field->setValue($this->getField($name));
            }

            foreach ($paramFields as $field) {
                if ($tabName) {
                    // Merge tab sets
                    if ($field instanceof TabSet) {
                        foreach ($field->Tabs() as $tab) {
                            $newTabName = preg_replace('#\.[^\.]+$#', '.', $tabName) . $tab->getName();
                            $fields->findOrMakeTab($newTabName, $tab->Title());
                            foreach ($tab->getChildren() as $child) {
                                $fields->addFieldToTab($newTabName, $child);
                            }
                        }
                    } else {
                        $fields->addFieldToTab($tabName, $field);
                    }
                } else {
                    $fields->push($field);
                }
            }
        }
    }

    function getField($key) {
        // Compound field handler
        if(substr($key,0,9)=='COMPOUND_') {
            list($dummy, $field, $subfield) = explode('_', $key, 3);
            $json = json_decode(parent::getField($field), true);
            if(isset($json[$subfield])) return $json[$subfield];

        } else {
            return parent::getField($key);
        }
    }

    function setField($key, $val) {
        // Compound field handler
        if(substr($key,0,9)=='COMPOUND_') {
            list($dummy, $field, $subfield) = explode('_', $key, 3);
            $json = json_decode(parent::getField($field), true);
            $json[$subfield] = $val;
            return parent::setField($field, json_encode($json));

        } else {
            return parent::setField($key, $val);
        }
    }

}
