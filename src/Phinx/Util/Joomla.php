<?php
namespace Phinx\Util;

use Joomla\CMS\Table\Table;
use Joomla\Utilities\ArrayHelper;

class Joomla
{
    protected $db;

    public function __construct($user_id = null)
    {
        Util::initJoomlaFramework($user_id);
        $this->db = \JFactory::getDbo();
    }

    public function removeExtensionFromDB($type, $element, $folder = null)
    {
        $extension = Table::getInstance('extension');

        $query = $this->db->getQuery(true);
        $query->select($this->db->qn('extension_id'))
            ->from($this->db->qn('#__extensions'))
            ->where($this->db->qn('type') . ' LIKE ' . $this->db->q($type))
            ->where($this->db->qn('element') . ' LIKE ' . $this->db->q($element));

        if ($folder) {
            $query->where($this->db->qn('folder') . ' LIKE ' . $this->db->q($folder));
        }
        $this->db->setQuery($query);
        $extension_id = $this->db->loadResult();

        if (!$extension_id) {
            return false;
        }

        // First order of business will be to load the component object table from the database.
        // This should give us the necessary information to proceed.
        if (!$extension->load((int) $extension_id)) {
            return false;
        }

        switch ($extension->type) {
            case 'component':
                $this->removeAdminMenus($extension);
                $this->removeSchema($extension);
                $this->removeAssets($extension);
                $this->removeCategories($extension);
                $this->removeUpdates($extension);
                // no break
            case 'file':
                $this->removeSchema($extension);
                // no break
            case 'module':
                $this->removeSchema($extension);
                $this->removeModules($extension);
                // no break
            case 'plugin':
                $this->removeSchema($extension);
                // no break
            case 'template':
                $this->removeTemplate($extension);
                // no break
            default:
                $this->removeExtension($extension);
        }
    }

    public function dropTable($table_name)
    {
        $db = \JFactory::getDbo();
        $db->setQuery('DROP TABLE IF EXISTS ' . $db->qn($table_name))->execute();
    }


    /**
     * This method removes the component from the admin menu.  It is 99% copied over from
     * Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     *
     * @return bool
     */
    protected function removeAdminMenus(\Joomla\CMS\Table\Extension $extension)
    {
        $menu = Table::getInstance('menu');

        //Get the ids of the menu items
        $query = $this->db->getQuery(true)
            ->select('id')
            ->from('#__menu')
            ->where($this->db->quoteName('client_id') . ' = 1')
            ->where($this->db->quoteName('menutype') . ' = ' . $this->db->q('main'))
            ->where($this->db->quoteName('component_id') . ' = ' . (int) $extension->extension_id);

        $this->db->setQuery($query);

        $ids = $this->db->loadColumn();

        $result = true;

        // Check for error
        if (!empty($ids)) {
            // Iterate the items to delete each one.
            foreach ($ids as $menuid) {
                if (!$menu->delete((int) $menuid, false)) {
                    $this->setError($menu->getError());

                    $result = false;
                }
            }

            // Rebuild the whole tree
            $menu->rebuild();
        }

        return $result;
    }

    /**
     * Remove the schema version
     *
     * Copied from Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeSchema(\Joomla\CMS\Table\Extension $extension)
    {
        $query = $this->db->getQuery(true)
            ->delete($this->db->qn('#__schemas'))
            ->where($this->db->qn('extension_id') . ' = ' . $this->db->q($extension->extension_id));
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Remove the component container in the assets table
     *
     * Copied from Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeAssets(\Joomla\CMS\Table\Extension $extension)
    {
        $asset = Table::getInstance('Asset');

        if ($asset->loadByName($extension->element)) {
            $asset->delete();
        }
    }

    /**
     * Remove and rebuild the component from the categories
     *
     * Copied from Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeCategories(\Joomla\CMS\Table\Extension $extension)
    {
        // Remove categories for this component
        $query = $this->db->getQuery(true)
            ->delete($this->db->qn('#__categories'))
            ->where($this->db->qn('extension') . '=' . $this->db->q($extension->element), 'OR')
            ->where($this->db->qn('extension') . ' LIKE ' . $this->db->q($extension->element . '.%'));
        $this->db->setQuery($query);
        $this->db->execute();

        // Rebuild the categories for correct lft/rgt
        $category = Table::getInstance('category');
        $category->rebuild();
    }

    /**
     * Remove any pending updates
     *
     * Copied from Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeUpdates(\Joomla\CMS\Table\Extension $extension)
    {
        $update = Table::getInstance('update');
        $uid = $update->find(
            [
                'element'   => $extension->element,
                'type'      => 'component',
                'client_id' => 1,
                'folder'    => '',
            ]
        );

        if ($uid) {
            $update->delete($uid);
        }
    }

    /**
     * Remove the extension for the extension table
     *
     * Copied from Joomla\CMS\Installer\Adapter\ComponentAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeExtension(\Joomla\CMS\Table\Extension $extension)
    {
        $extension->delete($extension->extension_id);
    }

    /**
     * Remove any modules created by the extensions
     *
     * Copied from Joomla\CMS\Installer\Adapter\ModuleAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     */
    protected function removeModules(\Joomla\CMS\Table\Extension $extension)
    {
        // Let's delete all the module copies for the type we are uninstalling
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' = ' . $this->db->quote($extension->element))
            ->where($this->db->quoteName('client_id') . ' = ' . (int) $extension->client_id);
        $this->db->setQuery($query);

        try {
            $modules = $this->db->loadColumn();
        } catch (\RuntimeException $e) {
            $modules = [];
        }

        // Do we have any module copies?
        if (count($modules)) {
            // Ensure the list is sane
            $modules = ArrayHelper::toInteger($modules);
            $modID = implode(',', $modules);

            // Wipe out any items assigned to menus
            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__modules_menu'))
                ->where($this->db->quoteName('moduleid') . ' IN (' . $modID . ')');
            $this->db->setQuery($query);

            try {
                $this->db->execute();
            } catch (\RuntimeException $e) {
                \JLog::add(\JText::sprintf('JLIB_INSTALLER_ERROR_MOD_UNINSTALL_EXCEPTION', $this->db->stderr(true)), \JLog::WARNING, 'jerror');
                $retval = false;
            }

            // Wipe out any instances in the modules table
            /** @var \JTableModule $module */
            $module = Table::getInstance('Module');

            foreach ($modules as $modInstanceId) {
                $module->load($modInstanceId);
                $module->delete();
            }
        }

        // Now we will no longer need the module object, so let's delete it and free up memory
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' = ' . $this->db->quote($extension->element))
            ->where($this->db->quote('client_id') . ' = ' . $extension->client_id);
        $this->db->setQuery($query);

        try {
            // Clean up any other ones that might exist as well
            $this->db->execute();
        } catch (\RuntimeException $e) {
            // Ignore the error...
        }
    }

    /**
     * Remove the template and reset the default
     *
     * Copied from Joomla\CMS\Installer\Adapter\TemplateAdapter
     *
     * @param \Joomla\CMS\Table\Extension $extension
     *
     * @return bool
     */
    protected function removeTemplate(\Joomla\CMS\Table\Extension $extension)
    {
        // Deny remove default template
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->qn('#__template_styles'))
            ->where($this->db->qn('home') . ' = ' . $this->db->q('1'))
            ->where($this->db->qn('template') . ' = ' . $this->db->q($extension->name));
        $this->db->setQuery($query);

        if ($this->db->loadResult() != 0) {
            return false;
        }

        // Set menu that assigned to the template back to default template
        $subQuery = $this->db->getQuery(true)
            ->select('s.id')
            ->from($this->db->qn('#__template_styles', 's'))
            ->where($this->db->qn('s.template') . ' = ' . $this->db->q(strtolower($extension->name)))
            ->where($this->db->qn('s.client_id') . ' = ' . $extension->client_id);
        $query->clear()
            ->update($this->db->qn('#__menu'))
            ->set($this->db->qn('template_style_id') . ' = 0')
            ->where($this->db->qn('template_style_id') . ' IN (' . (string) $subQuery . ')');
        $this->db->setQuery($query);
        $this->db->execute();

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__template_styles'))
            ->where($this->db->quoteName('template') . ' = ' . $this->db->quote($extension->name))
            ->where($this->db->quoteName('client_id') . ' = ' . $extension->client_id);
        $this->db->setQuery($query);
        $this->db->execute();
    }

    /**
     * Upgrading from versions prior to 3.7.0 seem to result the core admin menus
     * not displaying.
     *
     * @since 3.7.0
     */
    public function fixMissingAdminMenu()
    {
        $query = $this->db->getQuery(true);
        $query->update($this->db->qn('#__menu'))
            ->set($this->db->qn('menutype') . '=' . $this->db->q('main'))
            ->where($this->db->qn('menutype') . '=' . $this->db->q('menu'))
            ->where($this->db->qn('client_id') . '=' . $this->db->q('1'));
        $this->db->setQuery($query)->execute();
    }
}
