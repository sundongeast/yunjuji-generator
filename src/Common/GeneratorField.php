<?php

namespace Yunjuji\Generator\Common;

use Illuminate\Support\Str;

class GeneratorField
{
    /** @var string */
    public $name;
    public $dbInput;
    public $htmlInput;
    public $htmlType;
    public $fieldType;

    /** @var array */
    public $htmlValues;

    /** @var string */
    public $migrationText;
    public $foreignKeyText;
    public $validations;

    /** @var bool */
    public $isSearchable = true;
    public $isFillable   = true;
    public $isPrimary    = false;
    public $inForm       = true;
    public $inIndex      = true;

    /**
     * tian add start
     */
    // displayField 关联显示的字段
    public $displayField;
    // form表单模式为 `laravel-admin` 时使用, 添加 `options` 字段
    public $options;
    // label
    public $label;
    // title
    public $title;
    public $width;
    public $columnType;
    /**
     * tian add end
     */

    public function parseDBType($dbInput)
    {
        $this->dbInput = $dbInput;
        $this->prepareMigrationText();
    }

    public function parseHtmlInput($htmlInput)
    {
        $this->htmlInput  = $htmlInput;
        $this->htmlValues = [];

        if (empty($htmlInput)) {
            $this->htmlType = 'text';

            return;
        }

        $inputsArr = explode(',', $htmlInput);

        $this->htmlType = array_shift($inputsArr);

        if (count($inputsArr) > 0) {
            $this->htmlValues = $inputsArr;
        }
    }

    public function parseOptions($options)
    {
        $options    = strtolower($options);
        $optionsArr = explode(',', $options);
        if (in_array('s', $optionsArr)) {
            $this->isSearchable = false;
        }
        if (in_array('p', $optionsArr)) {
            // if field is primary key, then its not searchable, fillable, not in index & form
            $this->isPrimary    = true;
            $this->isSearchable = false;
            $this->isFillable   = false;
            $this->inForm       = false;
            $this->inIndex      = false;
        }
        if (in_array('f', $optionsArr)) {
            $this->isFillable = false;
        }
        if (in_array('if', $optionsArr)) {
            $this->inForm = false;
        }
        if (in_array('ii', $optionsArr)) {
            $this->inIndex = false;
        }
    }

    private function prepareMigrationText()
    {
        $inputsArr           = explode(':', $this->dbInput);
        $this->migrationText = '$table->';

        $fieldTypeParams = explode(',', array_shift($inputsArr));
        $this->fieldType = array_shift($fieldTypeParams);
        $this->migrationText .= $this->fieldType . "('" . $this->name . "'";

        if ($this->fieldType == 'enum') {
            $this->migrationText .= ', [';
            foreach ($fieldTypeParams as $param) {
                $this->migrationText .= "'" . $param . "',";
            }
            $this->migrationText = substr($this->migrationText, 0, strlen($this->migrationText) - 1);
            $this->migrationText .= ']';
        } else {
            foreach ($fieldTypeParams as $param) {
                $this->migrationText .= ', ' . $param;
            }
        }

        $this->migrationText .= ')';

        foreach ($inputsArr as $input) {
            $inputParams  = explode(',', $input);
            $functionName = array_shift($inputParams);
            if ($functionName == 'foreign') {
                $foreignTable = array_shift($inputParams);
                $foreignField = array_shift($inputParams);

                // $this->foreignKeyText .= "\$table->foreign('".$this->name."')->references('".$foreignField."')->on('".$foreignTable."');";
                /**
                 * tian add 默认将所有的外键关系设为级联 start
                 */
                $this->foreignKeyText .= "\$table->foreign('" . $this->name . "')->references('" . $foreignField . "')->on('" . $foreignTable . "')->onUpdate('cascade')->onDelete('cascade');";
                /**
                 * tian add 默认将所有的外键关系设为级联 end
                 */
            } else {
                $this->migrationText .= '->' . $functionName;
                $this->migrationText .= '(';
                $this->migrationText .= implode(', ', $inputParams);
                $this->migrationText .= ')';
            }
        }

        $this->migrationText .= ';';
    }

    public static function parseFieldFromFile($fieldInput)
    {
        $field       = new self();
        $field->name = $fieldInput['name'];
        $field->parseDBType($fieldInput['dbType']);
        $field->parseHtmlInput(isset($fieldInput['htmlType']) ? $fieldInput['htmlType'] : '');
        $field->validations  = isset($fieldInput['validations']) ? $fieldInput['validations'] : '';
        $field->isSearchable = isset($fieldInput['searchable']) ? $fieldInput['searchable'] : false;
        $field->isFillable   = isset($fieldInput['fillable']) ? $fieldInput['fillable'] : true;
        $field->isPrimary    = isset($fieldInput['primary']) ? $fieldInput['primary'] : false;
        $field->inForm       = isset($fieldInput['inForm']) ? $fieldInput['inForm'] : true;
        $field->inIndex      = isset($fieldInput['inIndex']) ? $fieldInput['inIndex'] : true;

        /**
         * tain add start
         */
        // `displayField` 关联显示的字段
        $field->displayField = isset($fieldInput['displayField']) ? explode(",", $fieldInput['displayField']) : array();
        // form表单模式为 `laravel-admin` 时使用, 添加 `options` 字段
        $field->options = isset($fieldInput['options']) ? $fieldInput['options'] : '';
        // label
        $field->label = isset($fieldInput['label']) ? $fieldInput['label'] : '';
        // title
        $field->title      = isset($fieldInput['title']) ? $fieldInput['title'] : '';
        $field->width      = isset($fieldInput['width']) ? $fieldInput['width'] : '';
        $field->columnType = isset($fieldInput['columnType']) ? $fieldInput['columnType'] : '';
        /**
         * tain add end
         */

        return $field;
    }

    public function __get($key)
    {
        if ($key == 'fieldTitle') {
            return Str::title(str_replace('_', ' ', $this->name));
        }

        return $this->$key;
    }
}
