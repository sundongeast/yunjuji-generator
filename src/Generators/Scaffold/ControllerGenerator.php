<?php

namespace Yunjuji\Generator\Generators\Scaffold;

use Illuminate\Support\Str;
use InfyOm\Generator\Generators\BaseGenerator;
use InfyOm\Generator\Utils\FileUtil;
use Yunjuji\Generator\Common\CommandData;

class ControllerGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $templateType;

    /** @var string */
    private $fileName;

    /**
     * tian add
     * [$baseTemplateType 基本的模板类型]
     * @var [type]
     */
    private $baseTemplateType;

    /**
     * tian add
     * [$formMode form的模式]
     * @var [type]
     */
    private $formMode = '';

    /**
     * tian add
     * [$formModePrefix description]
     * @var [type]
     */
    private $formModePrefix = '';

    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path        = $commandData->config->pathController;

        /**
         * tian comment start
         */
        // $this->templateType = config('infyom.laravel_generator.templates', 'core-templates');
        /**
         * tian comment end
         */

        /**
         * tian add start
         */
        $this->templateType     = config('yunjuji.generator.templates.extend', 'core-templates');
        $this->baseTemplateType = config('yunjuji.generator.templates.base', 'yunjuji-generator');
        if ($this->commandData->getOption('formMode')) {
            $this->formMode       = $this->commandData->getOption('formMode');
            $this->formModePrefix = $this->formMode . '.';
        }
        /**
         * tian add end
         */

        $this->fileName = $this->commandData->modelName . 'Controller.php';
    }

    /**
     * [generate 【产生】]
     * @return [type] [description]
     */
    public function generate()
    {
        /**
         * tian comment start
         */
        // if ($this->commandData->getAddOn('datatables')) {
        //     $templateData = get_template('scaffold.controller.datatable_controller', 'laravel-generator');

        //     $this->generateDataTable();
        // } else {
        //     $templateData = get_template('scaffold.controller.controller', 'laravel-generator');

        //     $paginate = $this->commandData->getOption('paginate');

        //     if ($paginate) {
        //         $templateData = str_replace('$RENDER_TYPE$', 'paginate('.$paginate.')', $templateData);
        //     } else {
        //         $templateData = str_replace('$RENDER_TYPE$', 'all()', $templateData);
        //     }
        // }
        /**
         * tian comment end
         */

        /**
         * tian add start
         */
        // 不同的模式选择不用的模板
        if ($this->formMode) {
            $templateData = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.controller', $this->baseTemplateType);
        } else {
            $templateData = yunjuji_get_template('scaffold.controller.controller', $this->baseTemplateType);
        }

        // 预加载模型关联
        $modelRelationsStr = '';
        $modelRelationsArr = [];
        // create, edit等函数里添加获取选项和给前端传递选项参数
        $fieldOptionValueStr     = '';
        $withFieldOptionValueStr = '';
        $withFieldOptionValueArr = [];

        // 增加多对多关联的store, update, destroy方法
        $modelRelationsStoreStr   = '';
        $modelRelationsUpdateStr  = '';
        $modelRelationsDestroyStr = '';

        // 遍历有关联的字段, 给原先的 `laravel-collection` 的 `form` 的 `select` 提供服务器端 `options`
        foreach ($this->commandData->hasRelationFields as $key => $relationField) {
            // `type`为 `mtm`的字段: 0代表第二张表的表名也是关系名, 1代表的是第三张表的表名, 2代表主表对应的字段, 3代表第二张表对应的字段
            // 如果关联字段是1t1或者是m2m,并且控件类型是select,并且没有选项值【说明是远端读取】
            if (($relationField['type'] == '1t1' || $relationField['type'] == 'mtm') && in_array($relationField['htmlType'], array('select')) && count($relationField['htmlValues']) == 0) {
                // 得到一个stub模板
                $optionsTemplateData = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.form-fields.get-options.' . $relationField['htmlType'], $this->baseTemplateType);
                // dd($optionsTemplateData);
                //进行模板值替换, 临时变量【temp+字段名+表名】
                $tempTableName = $relationField['displayField'][0];
                if (substr($tempTableName, -1) != 's') {
                    $tempTableName .= 's';
                }
                $optionsTemplateData = str_replace('$TEMP_FIELD_OPTIONS$', 'temp_' . $relationField['name'] . '_' . $relationField['displayField'][0] . 's', $optionsTemplateData);
                // 传递给前端的变量
                $optionsTemplateData = str_replace('$FIELD_OPTIONS$', $relationField['name'] . '_' . $relationField['displayField'][0] . 's', $optionsTemplateData);
                $optionsTemplateData = str_replace('$TEMP_FIELD_OPTION$', 'temp_' . $relationField['name'] . '_' . $relationField['displayField'][0], $optionsTemplateData);
                // 表名
                $optionsTemplateData = str_replace('$TABLE_NAME$', $tempTableName, $optionsTemplateData);
                // key
                $optionsTemplateData = str_replace('$KEY$', $relationField['displayField'][1], $optionsTemplateData);
                // value
                $optionsTemplateData = str_replace('$VALUE$', $relationField['displayField'][2], $optionsTemplateData);
                // 添加到变量 `$fieldOptionValueStr` 和 `$withFieldOptionValueStr` 里面
                $fieldOptionValueStr .= $optionsTemplateData;
                $withFieldOptionValueArr[] = '\'' . $relationField['name'] . '_' . $relationField['displayField'][0] . 's' . '\' => $' . $relationField['name'] . '_' . $relationField['displayField'][0] . 's';
            }

            // 关联字段是1t1改为【1t1或者是m2m】
            if ($relationField['type'] == 'mtm') {
                //记住mtm是有五个参数, 1t1是有四个参数
                $tempRelationName = camel_case($relationField['inputs'][0]);
                if (substr($tempRelationName, -1) != 's') {
                    $tempRelationName .= 's';
                }
                $modelRelationsArr[] = $tempRelationName;

                // 增加多对多关联的store, update, destroy方法
                // 新增
                $mtmStoreTemplateData = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.custom.relations.mtm.store', $this->baseTemplateType);
                $mtmStoreTemplateData = fill_template($this->commandData->dynamicVars, $mtmStoreTemplateData);
                // 替换字段【关联名和对应的字段表】
                $mtmStoreTemplateData = str_replace('$RELATION_NAME$', $tempRelationName, $mtmStoreTemplateData);
                $mtmStoreTemplateData = str_replace('$FIELD_NAME$', $relationField['inputs'][0], $mtmStoreTemplateData);
                $modelRelationsStoreStr .= $mtmStoreTemplateData;
                // 编辑
                $mtmUpdateTemplateData = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.custom.relations.mtm.update', $this->baseTemplateType);
                $mtmUpdateTemplateData = fill_template($this->commandData->dynamicVars, $mtmUpdateTemplateData);
                // 替换字段【关联名和对应的字段表】
                $mtmUpdateTemplateData = str_replace('$RELATION_NAME$', $tempRelationName, $mtmUpdateTemplateData);
                $mtmUpdateTemplateData = str_replace('$FIELD_NAME$', $relationField['inputs'][0], $mtmUpdateTemplateData);
                $modelRelationsUpdateStr .= $mtmUpdateTemplateData;
                // 删除
                $mtmDestoryTemplateData = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.custom.relations.mtm.destroy', $this->baseTemplateType);
                $mtmDestoryTemplateData = fill_template($this->commandData->dynamicVars, $mtmDestoryTemplateData);
                // 替换字段【关联名和对应的字段表】
                $mtmDestoryTemplateData = str_replace('$RELATION_NAME$', $tempRelationName, $mtmDestoryTemplateData);
                $mtmDestoryTemplateData = str_replace('$RELATION_FIELD$', $relationField['inputs'][2], $mtmDestoryTemplateData);
                $modelRelationsDestroyStr .= $mtmDestoryTemplateData;
            } else if ($relationField['type'] == '1t1') {
                $modelRelationsArr[] = camel_case($relationField['inputs'][0]) . $relationField['number'];
            } else if (in_array($relationField['type'], array('hmt', 'mhm', 'mhtm'))) {
                // 【多对多多态, 多态, 远程一对多】
                $modelRelationsArr[] = camel_case($relationField['inputs'][0]);
            }
        }

        // 如果有关联的话，替换变量，否则替换为空
        if (count($modelRelationsArr) > 0) {
            $modelRelationsStr = implode('\',\'', $modelRelationsArr);
            $modelRelationsStr = 'with([\'' . $modelRelationsStr . '\'])->';
            // datatables函数里面添加预加载with()
            $templateData = str_replace('$MODEL_RELATIONS$', $modelRelationsStr, $templateData);
            // create edit函数里面添加【获得选项】
            $templateData = str_replace('$FIELD_OPTION_VALUE$', $fieldOptionValueStr, $templateData);
            $templateData = str_replace('$WITH_FIELD_OPTION_VALUE$', '->with([' . implode(',', $withFieldOptionValueArr) . '])', $templateData);
        } else {
            $templateData = str_replace('$MODEL_RELATIONS$', '', $templateData);
            // create edit函数里面添加【获得选项】
            $templateData = str_replace('$FIELD_OPTION_VALUE$', '', $templateData);
            $templateData = str_replace('$WITH_FIELD_OPTION_VALUE$', '', $templateData);
        }

        // 增加多对多关联的store, update, destroy方法
        // 新增
        $templateData = str_replace('$MODEL_M2M_RELATIONS_STORE$', $modelRelationsStoreStr, $templateData);
        // 编辑
        $templateData = str_replace('$MODEL_M2M_RELATIONS_UPDATE$', $modelRelationsUpdateStr, $templateData);
        // 删除
        $templateData = str_replace('$MODEL_M2M_RELATIONS_DESTORY$', $modelRelationsDestroyStr, $templateData);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        // 【form表单模式为laravel-admin时使用，添加options字段】
        if ($this->formMode) {
            if ($this->formMode == 'laravel-backpack') {
                // 字段列表
                $strBackpackFieldList = '';
                // 如果需要则使用rbac的模板
                if ($this->commandData->getOption('rbac')) {
                    $strFieldTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.rbac_field', $this->baseTemplateType);
                } else {
                    // 单个字段的模板
                    $strFieldTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.field', $this->baseTemplateType);
                }

                // 遍历普通字段和m2m关联字段
                foreach (array_merge($this->commandData->fields, $this->commandData->hasM2mRelationFields) as $key => $field) {
                    if (!$field->inForm) {
                        continue;
                    }

                    // 判断是否有name字段
                    $flag = false;
                    // 字段选项
                    $arrOptions = [];
                    // 控件类型
                    if (!empty($field->htmlType)) {
                        $arrOptions['type'] = $field->htmlType;
                    }
                    // name
                    if (!empty($field->name)) {
                        $arrOptions['name']  = $field->name;
                        $arrOptions['label'] = $field->name;
                        $flag                = true;
                    }
                    // label
                    if (!empty($field->label)) {
                        $arrOptions['label'] = $field->label;
                    }
                    // `displayField` 通过它去判断需要那个字段当可以 `key`, 那个字段当 `value`
                    if (count($field->displayField) > 0) {
                        $arrOptions['attribute'] = $field->displayField[2];
                        // 临时变量模型名【因为现在提供的是表名, 默认认为模型名有s】
                        $tempModelName = $relationField['displayField'][0];
                        if (substr($tempModelName, -1) != 's') {
                            $tempModelName .= 's';
                        }
                        $arrOptions['model'] = $this->commandData->config->nsModel . '\\' . $tempModelName;
                        $arrOptions['model'] = str_replace("\\\\", "\\", $arrOptions['model']);
                    }
                    if ($flag) {
                        if (!empty($field->options)) {
                            $arrOptions = array_merge($arrOptions, json_decode(json_encode($field->options), true));
                        }
                        // 针对rbac时每个字段给它加上rbac控制,所以要进行变量替换
                        $strTempFieldTemplate = $strFieldTemplate;
                        $strTempFieldTemplate = fill_template_with_field_data(
                            $this->commandData->dynamicVars,
                            $this->commandData->fieldNamesMapping,
                            $strTempFieldTemplate,
                            $field
                        );
                        $strBackpackFieldList .= str_replace('$OPTIONS$', var_export($arrOptions, true), $strTempFieldTemplate . "\n");
                    }
                }
                // 替换模板
                $templateData = str_replace('$BACKPACK_FIELD_LIST$', $strBackpackFieldList, $templateData);
            } else if ($this->formMode == 'laravel-admin') {
                // 获取 `grid` 的 `column` 的模板
                $strGridColumnTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.grid.column', $this->baseTemplateType);
                // `form` 的 `get options`. 获取遍历数据的模板
                $strFormGetOptionsTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.form-fields.display.get-options', $this->baseTemplateType);
                // `form` 的 `option`. 选项的模板[->option($OPTIONS$);]
                $strFormOptionTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.form-fields.options', $this->baseTemplateType);
                // `form` 的 `field`. [$form->$FORMOPTIONS$;]
                $strFormFieldTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.form-fields.form', $this->baseTemplateType);
                // `filter` 的 `options`. 过滤区域的选项值
                $strFilterGetOptionsTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.controller.filter.get-options', $this->baseTemplateType);
                // 用来拼接 `laravel-admin` `form` 中的字段
                $strLaravelAdminFieldList = '';
                // 用来拼接 `laravel-admin` 的 `grid` 中的 `column` 字段
                $strLaravelAdminColumnList = '';
                // 用来凭借 `laravel-admin` 的 `filter` 中的字段
                $strLaravelAdminFilterFieldList = '';
                // 模型的命名空间
                $namespaceModelList = [];

                // `laravel-admin` 的 `form` 里面的字段, 遍历 `普通字段` 和 `m2m` 关联字段
                foreach (array_merge($this->commandData->fields, $this->commandData->hasM2mRelationFields) as $key => $field) {
                    // 如果不需要在 `form` 显示, 则跳过
                    if (!$field->inForm) {
                        continue;
                    }

                    // 判断是否有name字段
                    $flag = false;
                    // 控件类型, 默认 `text`
                    $strHtmlType = 'text';
                    // name
                    $name = '';
                    // label
                    $label = '';
                    // 控件类型
                    if (!empty($field->htmlType)) {
                        $strHtmlType = $field->htmlType;
                    }
                    // name
                    if (!empty($field->name)) {
                        $name  = $field->name;
                        $label = $field->name;
                        $flag  = true;
                    }
                    // label
                    if (!empty($field->label)) {
                        $label = $field->label;
                    }

                    // 用来记录 `选项值` 是 `模型->pluck()` 获得还是通过 `json中的options参数` 获得的标志
                    $optionsFlag = false;
                    // 循环获取 `displayField` 中指定 `模型名` 所要取得的 `字段` 数据
                    if (count($field->displayField) > 0) {
                        // 用来记录 `选项值` 是 `模型->pluck()` 获得还是通过 `json中的options参数` 获得的标志
                        $optionsFlag = true;
                        // 数据的变量名, `str_plural` 函数把字符串转换成复数形式
                        $optionsFieldOptions = str_finish(camel_case($field->displayField[0]), 's');
                        // 模型名
                        $optionsModelName = $field->displayField[0];
                        // 键[key]
                        $optionsKey = $field->displayField[1];
                        // 值[value]
                        $optionsValue = $field->displayField[2];
                        // 拼接查询数据库需要的模板
                        $strModelDisplay    = str_replace('$FIELD_OPTIONS$', $optionsFieldOptions, $strFormGetOptionsTemplate);
                        $strModelDisplay    = str_replace('$MODEL_NAME$', $optionsModelName, $strModelDisplay);
                        $strModelDisplay    = str_replace('$KEY$', $optionsKey, $strModelDisplay);
                        $strModelDisplay    = str_replace('$VALUE$', $optionsValue, $strModelDisplay) . yunjuji_nl();
                        $strOptionTemplates = str_replace('$OPTIONS$', '$' . $optionsFieldOptions, $strFormOptionTemplate);
                        $strFromType        = $strHtmlType . '(\'' . $name . '\', \'' . $label . '\')' . $strOptionTemplates;
                        $strLaravelAdminFieldList .= $strModelDisplay;
                        // 如果字段的options需要用到模型, 则需要在命名空间中引入该模型
                        if (!in_array($optionsModelName, $namespaceModelList)) {
                            $namespaceModelList[] = $this->commandData->namespaceModelMapping[$optionsModelName];
                        }
                    } else {
                        $strFromType = $strHtmlType . '(\'' . $name . '\', \'' . $label . '\')';
                        // 需要三个参数的字段
                        if (in_array($field->htmlType, array('timeRange', 'map', 'dateRange', 'datetimeRange'))) {
                            $second_field_name = $field->htmlType . 'Second';
                            if (!empty($field->options['second_field_name'])) {
                                $second_field_name = $field->options['second_field_name'];
                            }
                            $strFromType = $strHtmlType . '(\'' . $name . '\', \'' . $second_field_name . '\', \'' . $label . '\')';
                        }
                    }
                    // 获取 `options` 中的属性和值, 略过 `second_field_name` 和 `options` 参数
                    if ($flag) {
                        if (!empty($field->options)) {
                            foreach ($field->options as $key => $value) {
                                // 需要三个参数的字段
                                if ($key == 'second_field_name') {
                                    continue;
                                }

                                // 做一个判断, 如果选项值已经读取数据库的了, 就略过这个选项
                                if ($optionsFlag && $key === "options") {
                                    continue;
                                }

                                // 值如果是数组, 则转换为数组
                                if (is_array($value)) {
                                    $value = var_export($value, true);
                                    $strFromType .= '->' . $key . '(' . $value . ')';
                                } else {
                                    $strFromType .= '->' . $key . '(\'' . $value . '\')';
                                }
                            }
                        }
                    }
                    $strLaravelAdminFieldList .= str_replace('$LARAVEL_ADMIN_FORM_OPTIONS$', $strFromType, $strFormFieldTemplate . "\n");
                }

                // `laravel-admin` 的 `form` 中字段列表
                $templateData = str_replace('$LARAVELADMIN_FIELD_LIST$', $strLaravelAdminFieldList, $templateData);

                // `laravel-admin` 的 `grid` 里面的字段, 遍历普通字段和m2m关联字段
                foreach (array_merge($this->commandData->fields, $this->commandData->hasM2mRelationFields) as $key => $field) {
                    if (!$field->inIndex) {
                        continue;
                    }

                    // 判断是否有name字段
                    $flag = false;
                    // name
                    $name = '';
                    // title
                    $title = '';
                    // name
                    if (!empty($field->name)) {
                        $name  = $field->name;
                        $title = $field->name;
                        // 如果需要
                        if (count($field->displayField) > 0) {
                            $name = camel_case($field->displayField[0]) . '.' . $field->displayField[2];
                        }
                        $flag = true;
                    }
                    // title
                    if (!empty($field->title)) {
                        $title = $field->title;
                    }

                    // 单列模板替换
                    if ($flag) {
                        $tempGridColumnData = str_replace('$FIELD_NAME$', $name, $strGridColumnTemplate);
                        $tempGridColumnData = str_replace('$FIELD_TITLE$', $title, $tempGridColumnData . "\n");
                        $strLaravelAdminColumnList .= $tempGridColumnData;
                    }
                }

                // 替换 `grid` 中的 `column` 模板
                $templateData = str_replace('$LARAVELADMIN_COLUMN_LIST$', $strLaravelAdminColumnList, $templateData);

                // 过滤字段
                if (count($this->commandData->filterFields) > 0) {
                    // 遍历过滤字段
                    foreach ($this->commandData->filterFields as $filterField) {
                        // name
                        $filterName = '';
                        // label, 默认 `name` 值
                        $filterLabel = '';
                        // 操作符, 默认 `like`
                        $filterOperator = 'like';
                        // 控件
                        $filterHtmlType = '';

                        // name
                        if (!empty($filterField->name)) {
                            $filterName = $filterField->name;
                            $filterLabel = $filterField->name;
                        }
                        // label
                        if (!empty($filterField->label)) {
                            $filterLabel = $filterField->label;
                        }
                        // 操作符
                        if (!empty($filterField->operator)) {
                            $filterOperator = $filterField->operator;
                        }
                        // 控件
                        if (!empty($filterField->htmlType)) {
                            $filterHtmlType = $filterField->htmlType;
                        }

                        // 如果某些 `过滤控件类型(htmlType)` 也需要 选项值`, 直接将 `过滤控件类型(htmlType)` 加进数组即可
                        if (in_array($filterHtmlType, array('select', 'multipleSelect'))) {
                            foreach (array_merge($this->commandData->fields, $this->commandData->hasM2mRelationFields) as $field) {
                                // 判断字段名跟过滤字段名是否一致
                                if ($field->name == $filterName) {
                                    // 在 `displayField` 有值的情况下进行
                                    if (count($field->displayField) > 0) {
                                        // 数据的变量名, `str_plural` 函数把字符串转换成复数形式
                                        $optionsFieldOptions = str_finish(camel_case($field->displayField[0]), 's');
                                        // 模型名
                                        $optionsModelName = $field->displayField[0];
                                        // 键[key]
                                        $optionsKey = $field->displayField[1];
                                        // 值[value]
                                        $optionsValue = $field->displayField[2];
                                        // 拼接查询数据库需要的模板
                                        $strFilterGetOptions   = str_replace('$FIELD_OPTIONS$', $optionsFieldOptions, $strFilterGetOptionsTemplate);
                                        $strFilterGetOptions    = str_replace('$MODEL_NAME$', $optionsModelName, $strFilterGetOptions);
                                        $strFilterGetOptions    = str_replace('$KEY$', $optionsKey, $strFilterGetOptions);
                                        $strFilterGetOptions    = str_replace('$VALUE$', $optionsValue, $strFilterGetOptions) . yunjuji_nl();

                                        if ($filterName) {
                                            $strLaravelAdminFilterFieldList .= yunjuji_tab(4*4) . $strFilterGetOptions;
                                            $strLaravelAdminFilterFieldList .= yunjuji_tab(4*4) . '$filter->' . $filterOperator . '(\'' . $filterName . '\', \''. $filterLabel . '\')';
                                            if ($filterHtmlType) {
                                                $strLaravelAdminFilterFieldList .= '->' . $filterHtmlType . '($' . $optionsFieldOptions . ')';
                                            }
                                            $strLaravelAdminFilterFieldList .= ';' . yunjuji_nl();
                                        }
                                    } else {
                                        // 有 `options` 参数时所做的操作
                                        $filterFlag = false;
                                        $options = 'array()';
                                        if ( !empty($field->options) && isset($field->options['options']) ) {
                                            $options = var_export($field->options['options'], true);
                                        }
                                        $strFilterGetOptions = '$options = '.$options;
                                        if ($filterName) {
                                            $strLaravelAdminFilterFieldList .= yunjuji_tab(4*4) . $strFilterGetOptions;
                                            $strLaravelAdminFilterFieldList .= yunjuji_tab(4*4) . '$filter->' . $filterOperator . '(\'' . $filterName . '\', \''. $filterLabel . '\')';
                                            if ($filterHtmlType) {
                                                $strLaravelAdminFilterFieldList .= '->' . $filterHtmlType . '($options)';
                                            }
                                            $strLaravelAdminFilterFieldList .= ';' . yunjuji_nl();
                                        }
                                    }
                                } else {
                                    // 没有选项, 要做异常处理
                                }
                            }
                        } else {
                            if ($filterName) {
                                $strLaravelAdminFilterFieldList .= yunjuji_tab(4*4) . '$filter->' . $filterOperator . '(\'' . $filterName . '\', \''. $filterLabel . '\')';
                                if ($filterHtmlType) {
                                    $strLaravelAdminFilterFieldList .= '->' . $filterHtmlType . '()';
                                }
                                $strLaravelAdminFilterFieldList .= ';' . yunjuji_nl();
                            }
                        }
                    }
                }

                // 替换 `filter`
                $templateData = str_replace('$LARAVELADMIN_FILTER_FIELD_LIST$', $strLaravelAdminFilterFieldList, $templateData);
                
                // 拼装 `模型命名空间`
                $aNamespaceModelList = [];
                foreach ($namespaceModelList as $key => $value) {
                    $aNamespaceModelList[] = 'use ' . $value . ';';
                }

                // 替换 `模型命名空间`
                $templateData = str_replace('$USE_NAMESPACE_MODEL_LIST$', implode('\n', $aNamespaceModelList), $templateData);
            }
        }
        /**
         * tian add end
         */

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\nController created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    /**
     * [generateDataTable 【产生datatables】]
     * @return [type] [description]
     */
    private function generateDataTable()
    {
        $templateData = yunjuji_get_template($this->formModePrefix . 'scaffold.datatable', $this->baseTemplateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $headerFieldTemplate = yunjuji_get_template($this->formModePrefix . 'scaffold.views.datatable_column', $this->templateType);

        $headerFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->inIndex) {
                continue;
            }
            $headerFields[] = $fieldTemplate = fill_template_with_field_data(
                $this->commandData->dynamicVars,
                $this->commandData->fieldNamesMapping,
                $headerFieldTemplate,
                $field
            );
        }

        $path = $this->commandData->config->pathDataTables;

        $fileName = $this->commandData->modelName . 'DataTable.php';

        $fields = implode(',' . infy_nl_tab(1, 3), $headerFields);

        $templateData = str_replace('$DATATABLE_COLUMNS$', $fields, $templateData);

        FileUtil::createFile($path, $fileName, $templateData);

        $this->commandData->commandComment("\nDataTable created: ");
        $this->commandData->commandInfo($fileName);
    }

    /**
     * [rollback 【回退】]
     * @return [type] [description]
     */
    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->fileName)) {
            $this->commandData->commandComment('Controller file deleted: ' . $this->fileName);
        }

        /**
         * tian comment start
         */
        // if ($this->commandData->getAddOn('datatables')) {
        //     if ($this->rollbackFile($this->commandData->config->pathDataTables, $this->commandData->modelName.'DataTable.php')) {
        //         $this->commandData->commandComment('DataTable file deleted: '.$this->fileName);
        //     }
        // }
        /**
         * tian comment end
         */
    }
}
