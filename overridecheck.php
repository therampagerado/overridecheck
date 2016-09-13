<?php
/**
 * 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class OverrideCheck
 */
class OverrideCheck extends Module
{
    /**
     * OverrideCheck constructor.
     */
    public function __construct()
    {
        $this->name = 'overridecheck';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        // Only check from Back Office
        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id) {
            if (version_compare(phpversion(), '5.3', '<')) {
                $this->context->controller->errors[] = $this->displayName.': '.$this->l('Your PHP version is not supported. Please upgrade to PHP 5.3 or higher.');
                $this->disable();
                return;
            }
        }

        $this->displayName = $this->l('Override check');
        $this->description = $this->l('Check which overrides are in use');
    }

    /**
     * Load the configuration form
     *
     * @return string
     */
    public function getContent()
    {
        if ((Tools::isSubmit('viewmodule') || Tools::isSubmit('updatemodule')) && Tools::isSubmit('id_override')) {
            $overrides = $this->findOverrides();

            foreach ($overrides as $override) {
                if ($override['id_override'] == Tools::getValue('id_override')) {
                    $module = $override['module_code'];
                }
            }

            if (isset($module) && $module != $this->l('Unknown')) {
                $link = Context::getContext()->link;
                $baseUrl = $link->getAdminLink('AdminModules', true);
                Tools::redirectAdmin($baseUrl.'&module_name='.$module.'&anchor='.Tools::ucfirst($module));
            }
        }

        return $this->renderOverrideList();
    }

    /**
     * @return string
     * @throws PrestaShopDatabaseException
     */
    protected function renderOverrideList()
    {
        $helperList = new HelperList();
        $helperList->shopLinkType = false;

        $overrides = $this->findOverrides();
        $skipActions = array();
        foreach ($overrides as $override) {
            if ($override['module_code'] == $this->l('Unknown')) {
                $skipActions['View'][] = (int) $override['id_override'];
            }
        }

        $helperList->bulk_actions = array(
            'delete' => array(
                'text'    => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
            ),
        );

        $helperList->simple_header = true;
        $helperList->actions = array('View');
        $helperList->list_skip_actions = $skipActions;
        $helperList->bulk_actions = array();

        $helperList->_defaultOrderBy = 'id_override';

        $fieldsList = array(
            'id_override' => array('title' => $this->l('ID'),             'type' => 'int',      'width' => 40),
            'override'    => array('title' => $this->l('Override'),       'type' => 'string',   'width' => 'auto'),
            'module_code' => array('title' => $this->l('Module code'),    'type' => 'string',   'width' => 'auto'),
            'module_name' => array('title' => $this->l('Module name'),    'type' => 'string',   'width' => 'auto'),
            'version'     => array('title' => $this->l('Module version'), 'type' => 'string',   'width' => 'auto'),
            'date'        => array('title' => $this->l('Installed on'),   'type' => 'datetime', 'width' => 'auto'),
        );

        $helperList->list_id = 'override';
        $helperList->identifier = 'id_override';
        $helperList->title = $this->l('Overrides');
        $helperList->token = Tools::getAdminTokenLite('AdminModules');
        $helperList->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helperList->table = 'module';

        return $helperList->generateList($overrides, $fieldsList);
    }

    /**
     * Find overrides
     *
     * @return array Overrides
     */
    protected function findOverrides()
    {
        $overrides = array();

        $overriddenClasses = array_keys($this->findOverriddenClasses());

        $idOverride = 1;
        foreach ($overriddenClasses as $overriddenClass) {
            $reflectionClass = new ReflectionClass($overriddenClass);
            $reflectionMethods = array_filter($reflectionClass->getMethods(), function ($reflectionMethod) use ($overriddenClass) {
                return $reflectionMethod->class == $overriddenClass;
            });

            if (!file_exists($reflectionClass->getFileName())) {
                continue;
            }
            $overrideFile = file($reflectionClass->getFileName());
            if (is_array($overrideFile)) {
                $overrideFile = array_diff($overrideFile, array("\n"));
            } else {
                $overrideFile = array();
            }
            foreach ($reflectionMethods as $reflectionMethod) {
                /** @var ReflectionMethod $reflectionMethod */
                $overriddenMethod = array(
                    'id_override' => (int) $idOverride,
                    'override'    => $reflectionMethod->class.'::'.$reflectionMethod->name,
                    'module_code' => $this->l('Unknown'),
                    'module_name' => $this->l('Unknown'),
                    'date'        => $this->l('Unknown'),
                    'version'     => $this->l('Unknown'),
                );
                if (preg_match('/module: (.*)/ism', $overrideFile[$reflectionMethod->getStartLine() - 5], $module)
                    && preg_match('/date: (.*)/ism', $overrideFile[$reflectionMethod->getStartLine() - 4], $date)
                    && preg_match('/version: ([0-9.]+)/ism', $overrideFile[$reflectionMethod->getStartLine() - 3], $version)) {
                    $overriddenMethod['module_code'] = trim($module[1]);
                    $module = Module::getInstanceByName(trim($module[1]));
                    if (Validate::isLoadedObject($module)) {
                        $overriddenMethod['module_name'] = $module->displayName;
                    }
                    $overriddenMethod['date'] = trim($date[1]);
                    $overriddenMethod['version'] = trim($version[1]);
                }
                $overrides[] = $overriddenMethod;
                $idOverride++;
            }
        }

        return $overrides;
    }

    /**
     * Find all override classes
     *
     * @return array Overridden classes
     */
    protected function findOverriddenClasses()
    {
        $hostMode = defined('_PS_HOST_MODE_') && _PS_HOST_MODE_;

        return $this->getClassesFromDir('override/classes/', $hostMode) + $this->getClassesFromDir('override/controllers/', $hostMode);
    }

    /**
     * Retrieve recursively all classes in a directory and its subdirectories
     *
     * @param string $path     Relative path from root to the directory
     * @param bool   $hostMode
     *
     * @return array
     */
    protected function getClassesFromDir($path, $hostMode = false)
    {
        $classes = array();
        $rootDir = $hostMode ? $this->normalizeDirectory(_PS_ROOT_DIR_) : _PS_CORE_DIR_.'/';

        foreach (scandir($rootDir.$path) as $file) {
            if ($file[0] != '.') {
                if (is_dir($rootDir.$path.$file)) {
                    $classes = array_merge($classes, $this->getClassesFromDir($path.$file.'/', $hostMode));
                } elseif (substr($file, -4) == '.php') {
                    $content = file_get_contents($rootDir.$path.$file);

                    $namespacePattern = '[\\a-z0-9_]*[\\]';
                    $pattern = '#\W((abstract\s+)?class|interface)\s+(?P<classname>'.basename($file, '.php').'(?:Core)?)'.'(?:\s+extends\s+'.$namespacePattern.'[a-z][a-z0-9_]*)?(?:\s+implements\s+'.$namespacePattern.'[a-z][\\a-z0-9_]*(?:\s*,\s*'.$namespacePattern.'[a-z][\\a-z0-9_]*)*)?\s*\{#i';

                    if (preg_match($pattern, $content, $m)) {
                        $classes[$m['classname']] = array(
                            'path'     => $path.$file,
                            'type'     => trim($m[1]),
                            'override' => true,
                        );

                        if (substr($m['classname'], -4) == 'Core') {
                            $classes[substr($m['classname'], 0, -4)] = array(
                                'path'     => '',
                                'type'     => $classes[$m['classname']]['type'],
                                'override' => true,
                            );
                        }
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * Normalize directory
     *
     * @param string $directory
     *
     * @return string
     */
    protected function normalizeDirectory($directory)
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    }
}
