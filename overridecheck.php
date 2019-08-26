<?php
/**
 * 2017-2018 thirty bees
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
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use OverrideCheckModule\OverrideVisitor;
use ThirtyBeesOverrideCheck\PhpParser\NodeTraverser;
use ThirtyBeesOverrideCheck\PhpParser\ParserFactory;
use ThirtyBeesOverrideCheck\PhpParser\PrettyPrinter\Standard as StandardPrinter;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__.'/vendor/autoload.php';

/**
 * Class OverrideCheck
 */
class OverrideCheck extends Module
{
    /**
     * OverrideCheck constructor.
     *
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'overridecheck';
        $this->tab = 'administration';
        $this->version = '1.1.0';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        // Only check from Back Office
        if (isset(Context::getContext()->employee->id) && Context::getContext()->employee->id) {
            if (version_compare(phpversion(), '5.5', '<')) {
                $this->context->controller->errors[] = $this->displayName.': '.$this->l('Your PHP version is not supported. Please upgrade to PHP 5.5 or higher.');
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
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws ReflectionException
     * @throws SmartyException
     */
    public function getContent()
    {
        $overrides = $this->findOverrides();
        if (Tools::isSubmit('deletemodule') && Tools::isSubmit('id_override')) {
            $idOverride = Tools::getValue('id_override');
            if (array_key_exists($idOverride, $overrides)) {
                $override = explode('::', $overrides[$idOverride]['override']);
                if ($this->removeMethod($override[0], $override[1])) {
                    $this->context->controller->confirmations[] = $this->l('The override has been removed');
                }
            } else {
                $this->context->controller->errors[] = $this->l('Override not found');
            }
        } elseif (Tools::isSubmit('submitBulkdeletemodule') && Tools::getValue('overrideBox')) {
            $idOverrides = Tools::getValue('overrideBox');
            $success = true;
            foreach ($idOverrides as $idOverride) {
                if (array_key_exists($idOverride, $overrides)) {
                    $override = explode('::', $overrides[$idOverride]['override']);
                    $success &= $this->removeMethod($override[0], $override[1]);
                }
            }
            if ($success) {
                $this->context->controller->confirmations[] = $this->l('The overrides have been removed');
            } else {
                $this->context->controller->errors[] = $this->l('Not all overrides could be removed');
            }
        } elseif ((Tools::isSubmit('viewmodule') || Tools::isSubmit('updatemodule')) && Tools::isSubmit('id_override')) {
            foreach ($overrides as $override) {
                if ($override['id_override'] == Tools::getValue('id_override')) {
                    $module = $override['module_code'];
                }
            }

            if (isset($module) && $module != $this->l('Unknown')) {
                $link = Context::getContext()->link;
                $baseUrl = $link->getAdminLink('AdminModules', true);
                Tools::redirectAdmin($baseUrl.'&module_name='.$module.'&anchor='.ucfirst($module));
            }
        }

        return $this->renderOverrideList();
    }

    /**
     * @return string
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws ReflectionException
     * @throws SmartyException
     */
    protected function renderOverrideList()
    {
        $helperList = new HelperList();
        $helperList->shopLinkType = false;

        $overrides = $this->findOverrides();
        $skipActions = [];
        foreach ($overrides as $override) {
            if ($override['module_name'] === $this->l('Unknown') || $override['deleted']) {
                $skipActions['view'][] = $override['id_override'];
            }
            if ($override['deleted']) {
                $skipActions['delete'][] = $override['id_override'];
            }
        }


        $helperList->simple_header = true;
        $helperList->actions = ['view', 'delete'];
        $helperList->list_skip_actions = $skipActions;
        $helperList->bulk_actions = [
            'delete' => [
                'text'    => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
            ],
        ];

        $helperList->_defaultOrderBy = 'id_override';

        $fieldsList = [
            'id_override' => ['title' => $this->l('ID'),             'type' => 'int',      'width' => 40],
            'override'    => ['title' => $this->l('Override'),       'type' => 'string',   'width' => 'auto', 'callback' => 'displayOverride',      'callback_object' => $this],
            'module_code' => ['title' => $this->l('Module code'),    'type' => 'string',   'width' => 'auto', 'callback' => 'displayModuleCode',    'callback_object' => $this],
            'module_name' => ['title' => $this->l('Module name'),    'type' => 'string',   'width' => 'auto'],
            'version'     => ['title' => $this->l('Module version'), 'type' => 'string',   'width' => 'auto', 'callback' => 'displayModuleVersion', 'callback_object' => $this],
            'date'        => ['title' => $this->l('Installed on'),   'type' => 'datetime', 'width' => 'auto'],
        ];

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
     * @throws Adapter_Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws ReflectionException
     */
    protected function findOverrides()
    {
        $overrides = [];

        $overriddenClasses = array_keys($this->findOverriddenClasses());

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
                $overrideFile = array_diff($overrideFile, ["\n"]);
            } else {
                $overrideFile = [];
            }
            foreach ($reflectionMethods as $reflectionMethod) {
                /** @var ReflectionMethod $reflectionMethod */
                $idOverride = substr(sha1($reflectionMethod->class.'::'.$reflectionMethod->name), 0, 10);
                $overriddenMethod = [
                    'id_override' => $idOverride,
                    'override'    => $reflectionMethod->class.'::'.$reflectionMethod->name,
                    'module_code' => $this->l('Unknown'),
                    'module_name' => $this->l('Unknown'),
                    'date'        => $this->l('Unknown'),
                    'version'     => $this->l('Unknown'),
                    'deleted'     => (Tools::isSubmit('deletemodule') && Tools::getValue( 'id_override') === $idOverride)
                        || (Tools::isSubmit('overrideBox') && in_array($idOverride, Tools::getValue('overrideBox'))),
                ];
                if (isset($overrideFile[$reflectionMethod->getStartLine() - 5])
                    && preg_match('/module: (.*)/ism', $overrideFile[$reflectionMethod->getStartLine() - 5], $module)
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
                $overrides[$idOverride] = $overriddenMethod;
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
     * @param string $path Relative path from root to the directory
     * @param bool   $hostMode
     *
     * @return array
     */
    protected function getClassesFromDir($path, $hostMode = false)
    {
        $classes = [];
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
                        $classes[$m['classname']] = [
                            'path'     => $path.$file,
                            'type'     => trim($m[1]),
                            'override' => true,
                        ];

                        if (substr($m['classname'], -4) == 'Core') {
                            $classes[substr($m['classname'], 0, -4)] = [
                                'path'     => '',
                                'type'     => $classes[$m['classname']]['type'],
                                'override' => true,
                            ];
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

    /**
     * @param string $className
     * @param string $method
     *
     * @return bool
     */
    protected function removeMethod($className, $method)
    {
        $success = true;

        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
        try {
            $reflection = new ReflectionClass($className);
            $filename = $reflection->getFileName();
            if (strpos(realpath($filename), realpath(_PS_OVERRIDE_DIR_)) === false) {
                $this->context->controller->errors[] = $this->l('The selected class is not an override. Please report this on GitHub, because this is a bug!');
                return false;
            }

            $stmts = $parser->parse(file_get_contents($filename));
            $traverser = new NodeTraverser();
            $prettyPrinter = new StandardPrinter;
            $traverser->addVisitor(new OverrideVisitor($method));
            $traverser->traverse($stmts);
            file_put_contents($filename, $prettyPrinter->prettyPrintFile($stmts));
            @unlink(_PS_ROOT_DIR_.'/'.PrestaShopAutoload::INDEX_FILE);
        } catch (ReflectionException $e) {
            $this->context->controller->errors[] = $this->l('Unable to remove override, could not find override file');
            return false;
        } catch (Error $e) {
            $this->context->controller->errors[] = $this->l('Unable to remove override').": Parse Error: {$e->getMessage()}";
            return false;
        }

        return $success;
    }

    /**
     * Display an override on the list
     *
     * @param string $id
     * @param string $tr
     *
     * @return string
     */
    public function displayOverride($id, $tr)
    {
        $removed = !empty($tr['deleted']) ? ' <div class="badge badge-danger">'.$this->l('Removed').'</div>' : '';

        return "<code>$id</code>$removed";
    }

    /**
     * Display module code
     *
     * @param string $id
     *
     * @return string
     */
    public function displayModuleCode($id)
    {
        return "<kbd>$id</kbd>";
    }

    /**
     * Display module version
     *
     * @param string $id
     *
     * @return string
     */
    public function displayModuleVersion($id)
    {
        return "<kbd>$id</kbd>";
    }
}
