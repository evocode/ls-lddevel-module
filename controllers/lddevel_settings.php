<?

	class LDDevel_Settings extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';

		private $module_id = 'lddevel';
		private $form_id = 'settings';

		protected $globalHandlers = array(
			'onSaveDevelConfig'
		);

		public function __construct()
		{
			parent::__construct();
			$this->app_module = 'system';
			$this->app_tab = 'system';
			$this->app_page = 'settings';
			$this->app_module_name = 'Quick Cache';
		}

		public function index()
		{
			try
			{
				$this->app_page_title =  'Settings';

				$this->viewData['form_model'] = LDDevel_ModuleSettings::create($this->module_id, $this->form_id);
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function index_onSave($module_id, $form_id)
		{
			try
			{
				$save = post('LDDevel_ModuleSettings', array());
				LDDevel_Class::create()->save_config($save);

				Phpr::$session->flash['success'] = 'Settings have been saved.';
				Phpr::$response->redirect(url($this->module_id.'/'.$this->form_id));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function onSaveDevelConfig()
		{
			if (!defined('NO_LDDEVEL'))
				define('NO_LDDEVEL', true);

			try
			{
				$save = post('LDDevel_ModuleSettings', array());
				LDDevel_Class::create()->save_config($save);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>