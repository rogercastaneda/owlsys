<?php
/**
 * Copyright 2012 Roger Castañeda
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 * @package OS\Application\Plugins
 * @author roger castañeda <rogercastanedag@gmail.com>
 * @version 1
 */
class OS_Plugins_Widget extends Zend_Controller_Plugin_Abstract
{
    /**
     * (non-PHPdoc)
     * @see Zend_Controller_Plugin_Abstract::preDispatch()
     */
    public function preDispatch (Zend_Controller_Request_Abstract $request)
    {
        try {
            $viewHelperAction = new Zend_View_Helper_Action();
            $navigation = Zend_Registry::get('Zend_Navigation');
            $navItem = $navigation->findBy('active', true);
            $auth = Zend_Auth::getInstance();
            /* @var $acl Zend_Acl */
            $acl = Zend_Registry::get('ZendACL');
            
            $mdlRoleMapper = Acl_Model_RoleMapper::getInstance();
            $mdlWidgetMapper = System_Model_WidgetMapper::getInstance();
            $mdlWidgetDetailMapper = System_Model_WidgetdetailMapper::getInstance();
            $mdlResourceMapper = Acl_Model_ResourceMapper::getInstance();
            $role = new Acl_Model_Role();
            if ($auth->hasIdentity()) {
                $identity = $auth->getIdentity();
                $mdlRoleMapper->find( intval($identity->role_id), $role );
            } else {
                $mdlRoleMapper->find( 3, $role );
            }
			
			$mdlWidget = new System_Model_Widget();
			$mdlResource = new Acl_Model_Resource();
			$mdlWidgetDetail = new System_Model_Widgetdetail();
            
            $hookXml = APPLICATION_PATH . '/configs/hooks.xml';
            $sxeHook = new SimpleXMLElement($hookXml, null, true);
            
            $hooks = array();
            foreach ($sxeHook as $hook) {
                $hooks[] = strval($hook);
            }
            
            $widgets = $mdlWidgetDetailMapper->getWidgetsByHooksAndItemId($navItem->id, $hooks);
            foreach ($widgets as $widget) {
                $hookContent = '';
                $params = array();
                $widgetParams = Zend_Json::decode($widget->getParams());
                foreach ( $widgetParams as $strParam => $valParam ) {
                    $params[ $strParam ] = $valParam;
                }
                $resource = strtolower($widget->getResource()->getModule().':'.$widget->getResource()->getController());
                $privilege = strtolower($widget->getResource()->getActioncontroller());
                if ( $acl->isAllowed($role->getId(), $resource, $privilege) ) {
                    $hookContent .= ($widget->getShowtitle() == 1) ? "<h3>".$widget->getTitle()."</h3>" : "";
                    $hookContent .= $viewHelperAction->action($widget->getResource()->getActioncontroller(), $widget->getResource()->getController(), $widget->getResource()->getModule(), $params);
                }
                Zend_Layout::getMvcInstance()->assign($widget->getPosition(), $hookContent);
            }
            
        } catch (Exception $e) {
//             Zend_Debug::dump($e->getMessage());
//             Zend_Debug::dump($e->getTraceAsString()); 
//                 die();
            try {
		        $writer = new Zend_Log_Writer_Stream(APPLICATION_LOG_PATH . 'plugins.log');
		        $logger = new Zend_Log($writer);
		        $logger->log($e->getMessage(), Zend_Log::ERR);
		    } catch (Exception $e) {
		    }
        }
    }
}