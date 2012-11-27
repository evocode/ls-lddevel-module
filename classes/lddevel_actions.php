<?php

class LDDevel_Actions extends Cms_ActionScope
{
	public function on_saveConfig($ajax_mode = true)
	{
		if (!defined('NO_LDDEVEL'))
			define('NO_LDDEVEL', true);

		try
		{
			$save = post('LDDevel_ModuleSettings', array());
			LDDevel_Class::create()->save_config($save);

			//Phpr::$session->flash['success'] = 'Settings have been saved.';
			//Phpr::$response->redirect(url($this->module_id.'/'.$this->form_id));
		}
		catch (Exception $ex)
		{
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}
}
