<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\User\Api;

use Pi;
use Pi\Application\Api\AbstractApi;
use Module\User\Form\UserForm;

/**
 * User profile form manipulation APIs
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class Form extends AbstractApi
{
    /**
     * @{inheritDoc}
     */
    protected $module = 'user';

    /**
     * Load form from with fields, supporting custom form
     *
     * @param string $name
     * @param bool $withFilter  To set InputFilter
     *
     * @return UserForm
     */
    public function loadForm($name, $withFilter = false)
    {
        $class = str_replace(' ', '', ucwords(
            str_replace(array('-', '_', '.', '\\', '/'), ' ', $name)
        ));
        $formClass = $class . 'Form';
        $formClassName = 'Custom\User\Form\\' . $formClass;
        if (!class_exists($formClassName)) {
            $formClassName = 'Module\User\Form\\' . $formClass;
            if (!class_exists($formClassName)) {
                $formClassName = 'Module\User\Form\UserForm';
            }
        }

        if ($withFilter) {
            list($elements, $filters) = $this->loadFields($name, $withFilter);
        } else {
            $elements   = $this->loadFields($name, $withFilter);
            $filters    = array();
        }

        $form = new $formClassName($name, $elements);
        if ($withFilter && $form instanceof UserForm) {
            $form->loadInputFilter($filters);
        }

        return $form;
    }

    /**
     * Load form elements from field config, supporting custom configs
     *
     * @param string $name
     * @param bool $withFilter  To return filters
     *
     * @return array
     */
    public function loadFields($name, $withFilter = false)
    {
        $elements   = array();
        $filters    = array();
        /*
        $filePath   = sprintf('user/config/form.%s.php', $name);
        $file       = Pi::path('custom/module') . '/' . $filePath;
        if (!file_exists($file)) {
            $file = Pi::path('module') . '/' . $filePath;
        }
        $config     = include $file;
        */
        $config     = $this->loadConfig($name);
        $meta       = Pi::registry('field', $this->module)->read();
        foreach ($config as $name => $value) {
            if (!$value || empty($value['element'])) {
                if (isset($meta[$name]) &&
                    $meta[$name]['type'] == 'compound'
                ) {
                    if (is_array($value)) {
                        $fields = $value;
                    }
                    $compoundElements = $this->getCompoundElement($name, $fields);
                    foreach ($compoundElements as $element) {
                        if ($element) {
                            $elements[] = $element;
                        }
                    }
                    if ($withFilter) {
                        $compoundFilters = $this->getCompoundFilter($name, $fields);
                        foreach ($compoundFilters as $filter) {
                            if ($filter) {
                                $filters[] = $filter;
                            }
                        }
                    }
                } else {
                    $element = $this->getElement($name);
                    if ($element) {
                        $elements[] = $element;
                    }
                    if ($withFilter) {
                        $filter = $this->getFilter($name);
                        if ($filter) {
                            $filters[] = $filter;
                        }
                    }
                }
            } else {
                if (!empty($value['element'])) {
                    if (empty($value['element']['name']) && is_string($name)) {
                        $value['element']['name'] = $name;
                    }
                    $elements[] = $value['element'];
                }
                if ($withFilter) {
                    if (!empty($value['filter'])) {
                        if (empty($value['filter']['name']) && is_string($name)) {
                            $value['filter']['name'] = $name;
                        }
                        $filters[] = $value['filter'];
                    }
                }
            }
        }

        if ($withFilter) {
            $result = array($elements, $filters);
        } else {
            $result = $elements;
        }

        return $result;
    }

    /**
     * Load form filters from config, supporting custom configs
     *
     * @param string $name
     *
     * @return array
     */
    public function loadFilters($name)
    {
        $filters    = array();
        $config     = $this->loadConfig($name);
        $meta       = Pi::registry('field', $this->module)->read();
        foreach ($config as $name => $value) {
            if (!$value || empty($value['element'])) {
                if (isset($meta[$name]) &&
                    $meta[$name]['type'] == 'compound'
                ) {
                    if (is_array($value)) {
                        $fields = $value;
                    }
                    $compoundFilters = $this->getCompoundFilter($name, $fields);
                    foreach ($compoundFilters as $filter) {
                        if ($filter) {
                            $filters[] = $filter;
                        }
                    }
                } else {
                    $filter = $this->getFilter($name);
                    if ($filter) {
                        $filters[] = $filter;
                    }
                }
            } else {
                if (!empty($value['filter'])) {
                    if (empty($value['filter']['name']) && is_string($name)) {
                        $value['filter']['name'] = $name;
                    }
                    $filters[] = $value['filter'];
                }
            }
        }

        return $filters;
    }

    /**
     * Load form specs from field config, supporting custom configs
     *
     * @param string $name
     *
     * @return array
     */
    protected function loadConfig($name)
    {
        $filePath   = sprintf('user/config/form.%s.php', $name);
        $file       = Pi::path('custom/module') . '/' . $filePath;
        if (!file_exists($file)) {
            $file = Pi::path('module') . '/' . $filePath;
        }
        $config     = include $file;
        $result     = array();
        foreach ($config as $key => $value) {
            if (false === $value) {
                continue;
            }
            if (!is_string($key)) {
                if (!$value) {
                    continue;
                }
                if (is_string($value)) {
                    $key    = $value;
                    $value  = array();
                }
            }
            $result[$key] = (array) $value;
        }

        return $result;
    }

    /**
     * Canonize form element for a field
     *
     * @param array $data
     * @return array
     */
    protected function canonizeElement($data, $compound = '')
    {
        $element = $data['edit']['element'];
        if ($compound) {
            $element['name'] = sprintf('%s-%s', $compound, $data['name']);
        } else {
            $element['name'] = $data['name'];
        }
        if (isset($data['edit']['options']) &&
            $data['edit']['options']
        ) {
            $element['options'] = $data['edit']['options'];
        } else {
            $element['options'] = array();
        }
        $element['options']['label'] = $data['title'];
        if (isset($data['edit']['attributes'])) {
            $element['attributes'] = $data['edit']['attributes'];
        }

        if (isset($data['is_required'])) {
            $element['attributes']['required']= $data['is_required'];
        }
        if (!empty($element['type']) && 'multi_checkbox' == $element['type']) {
            $element['attributes']['required']= 0;
        }

        return $element;
    }

    /**
     * Canonize form element filter for a field
     *
     * @param array $data
     * @return array
     */
    protected function canonizeFilter($data, $compound = '')
    {
        $result = array();
        if (!empty($data['edit']['filters'])) {
            $result['filters'] = $data['edit']['filters'];
        }
        if (!empty($data['edit']['validators'])) {
            $result['validators'] = $data['edit']['validators'];
        }
        if (!empty($data['is_required'])) {
            $result['required']= $data['is_required'];
        }
        if (!empty($data['edit']['element']['type'])
            && 'multi_checkbox' == $data['edit']['element']['type']
        ) {
            $result['required']= empty($data['is_required']) ? 0 : 1;
        }
        if ($result) {
            if ($compound) {
                $result['name'] = sprintf('%s-%s', $compound, $data['name']);
            } else {
                $result['name'] = $data['name'];
            }
        }

        return $result;
    }

    /**
     * Get form element for field
     *
     * @param string $name
     * @return array
     */
    public function getElement($name)
    {
        $element = array();
        $elements = Pi::registry('field', $this->module)->read();
        if (isset($elements[$name]) && isset($elements[$name]['edit'])) {
            $element = $this->canonizeElement($elements[$name]);
        }

        return $element;
    }

    /**
     * Get form filter for field
     *
     * @param string $name
     * @return array
     */
    public function getFilter($name)
    {
        $result = array(
            'name'  => $name,
        );
        $elements = Pi::registry('field', $this->module)->read();
        if (isset($elements[$name]) && isset($elements[$name]['edit'])) {
            $result = $this->canonizeFilter($elements[$name]);
        }

        return $result;
    }

    /**
     * Get a compound field element if specified, or a compound's all fields
     * if field name is not specified
     *
     * @param string $compound
     * @param string $field
     * @return array
     */
    public function getCompoundElement($compound, $field = '')
    {
        $result = array();
        $elements = Pi::registry('compound_field', $this->module)->read($compound);
        if ($field) {
            $fields = (array) $field;
            foreach ($fields as $name) {
                if (isset($elements[$name])) {
                    $result[$name] = $this->canonizeElement($elements[$name], $compound);
                }
            }
            if (is_scalar($field)) {
                $result = $result[$field];
            }
        } else {
            foreach ($elements as $key => $element) {
                $result[$key] = $this->canonizeElement($element, $compound);
            }
        }

        return $result;
    }

    /**
     * Get a compound field element if specified, or a compound's all fields
     * if field name is not specified
     *
     * @param string $compound
     * @param string $field
     * @return array
     */
    public function getCompoundFilter($compound, $field = '')
    {
        $result = array();
        $elements = Pi::registry('compound_field', $this->module)->read($compound);
        if ($field) {
            $fields = (array) $field;
            foreach ($fields as $name) {
                if (isset($elements[$name])) {
                    $result[$name] = $this->canonizeFilter($elements[$name], $compound);
                }
            }
            if (is_scalar($field)) {
                $result = $result[$field];
            }
        } else {
            foreach ($elements as $key => $element) {
                $result[$key] = $this->canonizeFilter($element, $compound);
            }
        }

        return $result;
    }
}
