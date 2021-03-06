<?php

namespace luya\cms\base;

use Yii;
use yii\base\Object;
use yii\helpers\Inflector;
use luya\helpers\Url;
use luya\helpers\ArrayHelper;
use luya\admin\base\TypesInterface;
use luya\cms\frontend\blockgroups\MainGroup;
use luya\cms\helpers\BlockHelper;

/**
 * Concret Block implementation based on BlockInterface.
 *
 * This is an use case for the block implemenation as InternBaseBlock fro
 * two froms of implementations.
 *
 * + {{\luya\cms\base\PhpBlock}}
 * + {{\luya\cms\base\TwigBlock}}
 *
 * @since 1.0.0-beta8
 * @author Basil Suter <basil@nadar.io>
 */
abstract class InternalBaseBlock extends Object implements BlockInterface, TypesInterface
{
    /**
     * Returns the configuration array.
     *
     * @return array
     */
    abstract public function config();
    
    private $_varValues = [];

    private $_cfgValues = [];

    private $_placeholderValues = [];

    private $_envOptions = [];

    const INJECTOR_VAR = 'var';
    
    const INJECTOR_CFG = 'cfg';

    protected function injectorSetup()
    {
        foreach ($this->injectors() as $varName => $injector) {
            $injector->setContext($this);
            $injector->varName = $varName;
            $injector->setup();
        }
    }
    
    /**
     * @var bool Enable or disable the block caching
     */
    public $cacheEnabled = false;
    
    /**
     * @var int The cache lifetime for this block in seconds (3600 = 1 hour), only affects when cacheEnabled is true. 0 means never expire.
     */
    public $cacheExpiration = 3600;
    
    /**
     * @var bool Choose whether block is a layout/container/segmnet/section block or not, Container elements will be optically displayed
     * in a different way for a better user experience. Container block will not display isDirty colorizing.
     */
    public $isContainer = false;

    /**
     * @var string Containing the name of the environment (used to find the view files to render). The
     * module(Name) can be started with the Yii::getAlias() prefix `@`, otherwhise the `@` will be
     * added automatically.
     */
    public $module = 'app';
    
    /**
     * Whether cache is enabled for this block or not.
     *
     * @return boolean
     */
    public function getIsCacheEnabled()
    {
        return $this->cacheEnabled;
    }
    
    /**
     * The time of cache expiration
     */
    public function getCacheExpirationTime()
    {
        return $this->cacheExpiration;
    }
    
    /**
     * Whether is an container element or not.
     */
    public function getIsContainer()
    {
        return $this->isContainer;
    }
    
    /**
     * Contains the class name for the block group class
     *
     * @return string The classname on which the block should be stored in.
     * @since 1.0.0-beta6
     */
    public function blockGroup()
    {
        return MainGroup::className();
    }
    
    /**
     * Injectors are like huge helper objects which are going to automate functions, configs and variable assignement.
     *
     * An example of an Injector which builds a select dropdown and assigns the active query data into the extra vars `foobar`.
     *
     * ```php
     * public function injectors()
     * {
     *     return [
     *         'foobar' => new cms\injector\ActiveQueryCheckbox([
     *             'query' => MyModel::find()->where(['id' => 1]),
     *             'type' => self::INJECTOR_VAR, // could be self::INJECTOR_CFG
     *         ]);
     *     ];
     * }
     * ```
     *
     * Now the generated injector ActiveQueryCheckbox is going to grab all informations from the defined query and assign
     * them into the extra var foobar. Now you can access `$extras['foobar']` which returns all seleced rows from the checkbox
     * you have assigend.
     */
    public function injectors()
    {
        return [];
    }
    
    /**
     * Return link for usage in ajax request, the link will call the defined callback inside
     * this block. All callback methods must start with `callback`. An example for a callback method:.
     *
     * ```php
     * public function callbackTestAjax($arg1)
     * {
     *     return 'hello callback test ajax with argument: arg1 ' . $arg1;
     * }
     * ```
     *
     * The above defined callback link can be created with the follow code:
     *
     * ```php
     * $this->createAjaxLink('TestAjax', ['arg1' => 'My Value for Arg1']);
     * ```
     *
     * The most convient way to assign the variable is via extraVars
     *
     * ```php
     * public function extraVars()
     * {
     *     return [
     *         'ajaxLinkToTestAjax' => $this->createAjaxLink('TestAjax', ['arg1' => 'Value for Arg1']),
     *     ];
     * }
     * ```
     *
     * @param string $callbackName The callback name in uppercamelcase to call. The method must exists in the block class.
     * @param array  $params       A list of parameters who have to match the argument list in the method.
     *
     * @return string
     */
    public function createAjaxLink($callbackName, array $params = [])
    {
        $params['callback'] = Inflector::camel2id($callbackName);
        $params['id'] = $this->getEnvOption('id', 0);
        return Url::toAjax('cms/block/index', $params);
    }

    /**
     * Contains the icon
     */
    public function icon()
    {
        return;
    }

    /**
     * Returns true if block is active in backend.
     *
     * @return bool
     */
    public function isAdminContext()
    {
        return ($this->getEnvOption('context', false) === 'admin') ? true : false;
    }

    /**
     * Returns true if block is active in frontend.
     *
     * @return bool
     */
    public function isFrontendContext()
    {
        return ($this->getEnvOption('context', false) === 'frontend') ? true : false;
    }

    /**
     * Sets a key => value pair in env options.
     *
     * @param string $key   The string to be set as key
     * @param mixed  $value The value that will be stored associated with the given key
     */
    public function setEnvOption($key, $value)
    {
        $this->_envOptions[$key] = $value;
    }

    /**
     * Get a env option by $key. If $key does not exist it will return given $default or false.
     *
     * @param $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getEnvOption($key, $default = false)
    {
        return (array_key_exists($key, $this->_envOptions)) ? $this->_envOptions[$key] : $default;
    }

    /**
     * Returns all environment/context informations where the block have been placed. Example Data.
     *
     * + id (unique identifier for the block in cms context)
     * + blockId (id in the block list database, which is not unique)
     * + context
     * + pageObject
     *
     * @return array Returns an array with key value parings.
     */
    public function getEnvOptions()
    {
        return $this->_envOptions;
    }

    /**
     * Sets placeholder values.
     *
     * @param array $placeholders The array to be set as placeholder values
     */
    public function setPlaceholderValues(array $placeholders)
    {
        $this->_placeholderValues = $placeholders;
    }
    
    public function getPlaceholderValues()
    {
        return $this->_placeholderValues;
    }
    
    /**
     *
     * @param unknown $placholder
     * @return boolean
     */
    public function getPlacholderValue($placholder)
    {
        return (isset($this->getPlaceholderValues()[$placholder])) ? $this->getPlaceholderValues()[$placholder] : false;
    }

    /**
     * User method to get the values inside the class.
     *
     * @param string $key     The name of the key you want to retrieve
     * @param mixed  $default A default value that will be returned if the key isn't found
     *
     * @return mixed
     */
    public function getVarValue($key, $default = false)
    {
        return (array_key_exists($key, $this->_varValues)) ? $this->_varValues[$key] : $default;
    }

    /**
     * Sets var values.
     *
     * @param array $values The array to be set as var values
     */
    public function setVarValues(array $values)
    {
        $this->_varValues = $values;
    }
    
    public function getVarValues()
    {
        return $this->_varValues;
    }

    /**
     * User method to get the cfgs inside the block.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getCfgValue($key, $default = false)
    {
        return (array_key_exists($key, $this->_cfgValues)) ? $this->_cfgValues[$key] : $default;
    }

    
    /**
     * Sets the config values.
     *
     * @param array $values The array to be set as config values
     */
    public function setCfgValues(array $values)
    {
        $this->_cfgValues = $values;
    }

    public function getCfgValues()
    {
        return $this->_cfgValues;
    }

    /**
     * @return array
     */
    public function extraVars()
    {
        return [];
    }
    
    public function addExtraVar($key, $value)
    {
        $this->_extraVars[$key] = $value;
    }
    
    private $_extraVars = [];
    
    // access from outside
    public function getExtraVarValues()
    {
        $this->_extraVars = ArrayHelper::merge($this->_extraVars, $this->extraVars());
        return $this->_extraVars;
    }
    
    private $_assignExtraVars = false;
    
    public function getExtraValue($key, $default = false)
    {
        if (!$this->_assignExtraVars) {
            $this->getExtraVarValues();
            $this->_assignExtraVars = true;
        }
        return (isset($this->_extraVars[$key])) ? $this->_extraVars[$key] : $default;
    }
    
    /**
     * Returns an array with additional help informations for specific field (var or cfg).
     *
     * @return array An array where the key is the cfg/var field var name and the value the helper text.
     */
    public function getFieldHelp()
    {
        return [];
    }

    private $_vars = [];
    
    /**
     * @return array
     */
    public function getConfigVarsExport()
    {
        $config = $this->config();
        
        if (isset($config['vars'])) {
            foreach ($config['vars'] as $item) {
                $this->_vars[] = (new BlockVar($item))->toArray();
            }
        }

        return $this->_vars;
    }

    public function addVar(array $varConfig)
    {
        $this->_vars[] = (new BlockVar($varConfig))->toArray();
    }
    
    /**
     * @return array
     */
    public function getConfigPlaceholdersExport()
    {
        return (array_key_exists('placeholders', $this->config())) ? $this->config()['placeholders'] : [];
    }
    
    private $_cfgs = [];

    /**
     * @return array
     */
    public function getConfigCfgsExport()
    {
        $config = $this->config();
        
        if (isset($config['cfgs'])) {
            foreach ($config['cfgs'] as $item) {
                $this->_cfgs[] = (new BlockCfg($item))->toArray();
            }
        }
        
        return $this->_cfgs;
    }
    
    public function addCfg(array $cfgConfig)
    {
        $this->_cfgs[] = (new BlockCfg($cfgConfig))->toArray();
    }
    
    /**
     * Returns the name of the twig or php file to be rendered.
     *
     * @return string The name of the twig or php file (example.twig, example.php)
     */
    public function getViewFileName($extension)
    {
        $className = get_class($this);
    
        if (preg_match('/\\\\([\w]+)$/', $className, $matches)) {
            $className = $matches[1];
        }
    
        return $className.'.'.$extension;
    }
    
    /**
     * Make sure the module contains its alias prefix @
     *
     * @return string The module name with alias prefix @.
     */
    protected function ensureModule()
    {
        $moduleName = $this->module;
        if (substr($moduleName, 0, 1) !== '@') {
            $moduleName = '@'.$moduleName;
        }
        
        return $moduleName;
    }
    
    /**
     * Create the options array for a zaa-select field based on an key value pairing
     * array.
     *
     * @deprecated Will be removed in 1.0.0
     * @param array $options The key value array pairing the select array should be created from.
     * @since 1.0.0-beta5
     */
    protected function zaaSelectArrayOption(array $options)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::selectArrayOption() instead.', E_USER_DEPRECATED);
        return BlockHelper::selectArrayOption($options);
    }
    
    /**
     * Create the Options list in the config for a zaa-checkbox-array based on an
     * key => value pairing array.
     *
     * @deprecated Will be removed in 1.0.0
     * @param array $options The array who cares the options with items
     * @since 1.0.0-beta5
     */
    protected function zaaCheckboxArrayOption(array $options)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::checkboxArrayOption() instead.', E_USER_DEPRECATED);
        return BlockHelper::checkboxArrayOption($options);
    }
    
    /**
     * Get all informations from an zaa-image-upload type:
     *
     * ```php
     * 'image' => $this->zaaImageUpload($this->getVarValue('myImage')),
     * ```
     *
     * apply a filter for the image
     *
     * ```php
     * 'imageFiltered' => $this->zaaImageUpload($this->getVarValue('myImage'), 'small-thumbnail'),
     * ```
     *
     * @deprecated Will be removed in 1.0.0
     * @param string|int $value Provided the value
     * @param boolean|string $applyFilter To apply a filter insert the identifier of the filter.
     * @param boolean $returnObject Whether the storage object should be returned or an array.
     * @return boolean|array Returns false when not found, returns an array with all data for the image on success.
     */
    protected function zaaImageUpload($value, $applyFilter = false, $returnObject = false)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::imageUpload() instead.', E_USER_DEPRECATED);
        return BlockHelper::imageUpload($value, $applyFilter, $returnObject);
    }
    
    /**
     * Return all informations for a file if exists
     *
     * ```php
     * 'file' => $this->zaaFileUpload($this->getVarValue('myFile')),
     * ```
     *
     * @deprecated Will be removed in 1.0.0
     * @param string|int $value Provided the value
     * @param boolean $returnObject Whether the storage object should be returned or an array.
     * @return boolean|array Returns false when not found, returns an array with all data for the image on success.
     */
    protected function zaaFileUpload($value, $returnObject = false)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::fileUpload() instead.', E_USER_DEPRECATED);
        return BlockHelper::fileUpload($value, $returnObject);
    }
    
    /**
     * Get the full array for the specific zaa-file-array-upload type
     *
     * ```php
     * 'fileList' => $this->zaaFileArrayUpload($this->getVarValue('files')),
     * ```
     *
     * Each array item will have all file query item data and a caption key.
     *
     * @deprecated Will be removed in 1.0.0
     * @param string|int $value The specific var or cfg fieldvalue.
     * @param boolean $returnObject Whether the storage object should be returned or an array.
     * @return array Returns an array in any case, even an empty array.
     */
    protected function zaaFileArrayUpload($value, $returnObject = false)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::fileArrayUpload() instead.', E_USER_DEPRECATED);
        return BlockHelper::fileArrayUpload($value, $returnObject);
    }

    /**
     * Get the full array for the specific zaa-file-image-upload type
     *
     * ```php
     * 'imageList' => $this->zaaImageArrayUpload($this->getVarValue('images')),
     * ```
     *
     * Each array item will have all file query item data and a caption key.
     *
     * @deprecated Will be removed in 1.0.0
     * @param string|int $value The specific var or cfg fieldvalue.
     * @param boolean|string $applyFilter To apply a filter insert the identifier of the filter.
     * @param boolean $returnObject Whether the storage object should be returned or an array.
     * @return array Returns an array in any case, even an empty array.
     */
    protected function zaaImageArrayUpload($value, $applyFilter = false, $returnObject = false)
    {
        trigger_error('Deprecated method '.__METHOD__.' in '.get_called_class().', use \luya\cms\helpers\BlockHelper::imageArrayUpload() instead.', E_USER_DEPRECATED);
        return BlockHelper::imageArrayUpload($value, $applyFilter, $returnObject);
    }
}
